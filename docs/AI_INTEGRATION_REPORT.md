# AI Integration in Ghorfa — Project Report Documentation

This document describes how artificial intelligence is used in **Ghorfa**, a Laravel-based property rental and sale platform. AI is applied in two main areas: **intelligent search** and **personalized recommendations**. Both run **silently** — users see normal search and “Recommended” results without any AI branding.

---

## 1. Overview & Design Philosophy

Ghorfa uses a **hybrid AI architecture**:

| Layer | Role | AI involvement |
|--------|------|----------------|
| **Traditional filtering** | Price, type, amenities, rules, coordinates | No AI |
| **Semantic search** | Natural-language location/keyword queries | OpenAI embeddings + bilingual expansion |
| **Recommendation engine** | Personalized property ranking | Hybrid ML-style scoring + optional embeddings |
| **Behavior tracking** | Views, clicks, likes, contacts, searches | Feeds the recommendation engine (not generative AI) |

**Design principles:**
- **Pre-compute property embeddings** at save time — not on every page load
- **Embed search queries at runtime** only when the user searches
- **Graceful degradation** — if OpenAI is unavailable, the app falls back to SQL LIKE search and attribute-based recommendations
- **Invisible UX** — semantic search activates automatically when the user types in the search box with “Recommended” sort; no “AI” labels in the UI

---

## 2. AI Technologies Used

| Technology | Model | Purpose |
|------------|-------|---------|
| **OpenAI Embeddings API** | `text-embedding-3-small` (default) | Convert property text and search queries into numeric vectors for similarity matching |
| **OpenAI Chat Completions API** | `gpt-4o-mini` (default) | Expand Arabic/English search variants when the offline dictionary has no match |
| **Cosine similarity** | Custom PHP implementation | Compare embedding vectors (0 = unrelated, 1 = identical) |
| **Collaborative filtering** | Custom algorithm (no external ML library) | Find similar users based on interaction overlap |
| **Content-based filtering** | Weighted attribute matching | Match properties to user preferences (type, city, price, amenities) |
| **Haversine distance** | Geographic formula | Score proximity when coordinates are available |

**External dependency:** OpenAI API (`OPENAI_API_KEY` in `.env`). All other logic is implemented in PHP within the Laravel application.

---

## 3. System Architecture

```
User Actions (Search, Map filters, View, Like, Contact)
        │
        ▼
Behavior Layer (PropertyInteractionService → property_interactions, property_searches)
        │
        ├──► SemanticSearchService ──► SearchQueryLocalizationService ──► OpenAI Chat API
        │         │
        │         └──► OpenAIEmbeddingService ──► OpenAI Embeddings API
        │
        └──► RecommendationService ──► OpenAIEmbeddingService
                    │
                    └──► property_embeddings + properties tables
```

---

## 4. Data Model (AI-Related Tables)

### 4.1 property_embeddings
Stores pre-generated vectors for each approved property.

| Column | Description |
|--------|-------------|
| property_id | FK to property (unique) |
| model | e.g. text-embedding-3-small |
| embedding | JSON array of floats (~1536 dimensions) |
| source_text | Concatenated text used to generate the vector |
| generated_at | Timestamp |

**Source text built from:** title, description, address, city, country, property type, listing type, amenities.

### 4.2 property_interactions
Weighted user behavior for collaborative and content filtering.

| Action | Weight | Meaning |
|--------|--------|---------|
| view | 1 | Property page viewed |
| click | 2 | “View Details” clicked |
| search_view | 2 | Click from search results |
| like | 5 | Favorited |
| contact | 8 | Inquiry / transaction contact |
| unlike | -5 | Removed from favorites |

### 4.3 property_searches
Stores each search session (filters, location query, result count) to infer location intent for recommendations.

### 4.4 Denormalized counters on properties
- views_count, likes_count — used in popularity scoring and kept in sync by PropertyInteractionService.

---

## 5. Feature A — Semantic Search

### 5.1 Purpose
Allow users to search using **natural language** and **Arabic or English** (e.g. ازاعي, بعلبك, beirut apartment) and get relevant properties even when exact SQL text match would fail.

### 5.2 Where it runs
- **List search:** /search, /filter-search (PropertyController::runPropertySearch)
- **Map search:** /map (MapController::index)
- **Activation:** Automatically when the user enters text in the location field, sort is Recommended (default), and OpenAI is configured

### 5.3 Algorithm (4-step pipeline)

**Step 1 — Query expansion (bilingual)**
1. Offline dictionary — Lebanese location aliases (Beirut, Baalbek, Ouzai, etc.)
2. OpenAI chat (optional) — For unknown queries, GPT returns JSON variants
3. Results are cached (default TTL: 3600 seconds)

**Step 2 — Keyword scoring**

| Field | Weight |
|-------|--------|
| city | 1.00 |
| title | 0.95 |
| address | 0.90 |
| description | 0.80 |
| country | 0.70 |
| Amenity names | 0.65 |

**Step 3 — Semantic (embedding) scoring**
1. Build enriched embedding input: original query + variant list
2. Call OpenAI Embeddings API → query vector
3. Compare with each property’s stored vector via cosine similarity

Formula: cosine(A, B) = (A · B) / (||A|| × ||B||)

**Step 4 — Hybrid ranking**

For each candidate property (up to 500):

finalScore = (keywordScore × 0.60) + (semanticScore × 0.40)

- If keywordScore > 0, add +0.35 bonus
- Exclude property if: keywordScore = 0 AND semanticScore < 0.25
- Sort: keyword score descending, then final score descending

### 5.4 Fallback behavior
If semantic search is off or OpenAI fails: standard SQL LIKE on country, city, address, title, description.

---

## 6. Feature B — Hybrid Recommendation System

### 6.1 Purpose
Surface “Recommended For You” properties on the home page, recommendations page (/recommendations), and as the default sort in search.

### 6.2 Final score formula (users with interaction history)

final = 0.40 × C + 0.25 × T + 0.25 × L + 0.10 × P

| Symbol | Component | Description |
|--------|-----------|-------------|
| C | Collaborative | Users with similar tastes liked/viewed this property |
| T | Content | Matches user’s preferred types, cities, prices, amenities |
| L | Location | Proximity to search area or recent search city |
| P | Popularity | Normalized views_count + likes_count × 3 |

### 6.3 Component algorithms

**A. Collaborative filtering (40%)**
- Build user weight vector from interactions
- Find neighbor users with overlapping property interactions
- Score unseen properties based on neighbor similarity × neighbor weights
- Normalize to 0–1

**B. Content-based filtering (25%) — includes AI**
- Build preference profile from weighted interactions (type, city, price, amenities, etc.)
- Score candidate against profile
- AI blend: contentScore = 0.70 × attributeScore + 0.30 × semanticSimilarity
- User preference vector = weighted average of embeddings from interacted properties

**C. Location score (25%)**

| Distance | Score |
|----------|-------|
| ≤ 1 km | 1.00 |
| ≤ 3 km | 0.85 |
| ≤ 5 km | 0.70 |
| ≤ 10 km | 0.45 |
| ≤ 20 km | 0.20 |
| > 20 km | 0.00 |

Uses Haversine formula (Earth radius 6371 km). Falls back to city/address text match when coordinates are missing.

**D. Popularity (10%)**
rawPopularity = views_count + (likes_count × 3), normalized among candidates.

### 6.4 Cold-start (new users / no history)

final = 0.35 × F + 0.30 × L + 0.20 × P + 0.15 × R

F = filter match, L = location, P = popularity, R = recency.

### 6.5 Caching
Recommendations cached per user + context for 900 seconds (15 minutes).

---

## 7. Feature C — Property Embedding Lifecycle

### 7.1 When embeddings are created
1. On property save — PropertyObserver for approved properties
2. Bulk backfill — php artisan properties:generate-embeddings

### 7.2 Runtime API usage

| When | API call | Cached? |
|------|----------|---------|
| Property approved/updated | Embeddings | No (stored in DB) |
| User searches | Embeddings (query) | Yes (1 hour) |
| Unknown Arabic/English query | Chat completions | Yes (1 hour) |
| Recommendations | None (uses stored vectors) | N/A |

---

## 8. Integration Points

| Event | Service | Feeds |
|-------|---------|-------|
| User searches (list or map) | SemanticSearchService + recordSearch() | Search results + property_searches |
| User opens property page | recordView() | Collaborative + content profile |
| User clicks “View Details” | trackClick | Stronger interest signal |
| User likes property | recordLike() | Content + collaborative |
| User contacts landlord | recordContact() | Strongest signal (weight 8) |
| Home / recommendations page | getRecommendations() | Personalized grid |
| Search sort = Recommended | applyRecommendedSort() | Reorders filtered results |

---

## 9. Configuration (Environment Variables)

```
OPENAI_API_KEY=
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_CHAT_MODEL=gpt-4o-mini

SEMANTIC_SEARCH_ENABLED=true
SEMANTIC_SEARCH_AUTO=true
SEMANTIC_SEARCH_MIN_SIMILARITY=0.25
SEMANTIC_SEARCH_KEYWORD_WEIGHT=0.60
SEMANTIC_SEARCH_SEMANTIC_WEIGHT=0.40
SEMANTIC_SEARCH_USE_AI_TRANSLATION=true

RECOMMENDATION_CACHE_TTL=900
RECOMMENDATION_SEMANTIC_BLEND=0.30
RECOMMENDATION_USE_OPENAI=true
```

---

## 10. Key Source Files

| File | Responsibility |
|------|----------------|
| app/Services/OpenAIEmbeddingService.php | OpenAI API, cosine similarity, vector storage |
| app/Services/SemanticSearchService.php | Hybrid keyword + semantic search ranking |
| app/Services/SearchQueryLocalizationService.php | Arabic ↔ English expansion |
| app/Services/RecommendationService.php | Hybrid recommendation engine |
| app/Services/PropertyInteractionService.php | Behavior tracking and weights |
| app/Http/Controllers/PropertyController.php | List search pipeline |
| app/Http/Controllers/MapController.php | Map search pipeline |
| app/Observers/PropertyObserver.php | Auto-generate embeddings on save |

---

## 11. Abstract (one paragraph)

Ghorfa integrates OpenAI embedding models and a custom hybrid recommendation engine to improve property discovery for Lebanese users. Property listings are converted into semantic vectors at creation time using text-embedding-3-small, enabling natural-language and bilingual search through a pipeline of query expansion, keyword matching, and cosine similarity ranking. A separate recommendation system combines collaborative filtering, content-based attribute matching, geographic proximity, and popularity scoring—with embeddings blended into content preferences for logged-in users. User interactions (views, clicks, likes, and contacts) are weighted and stored to continuously refine recommendations. All AI features operate transparently within normal search and “Recommended” sorting, providing intelligent results without exposing technical complexity to end users.

---

## 12. Limitations & Future Work

| Limitation | Possible improvement |
|------------|----------------------|
| Embeddings generated synchronously on save | Queue background jobs for scale |
| Collaborative filtering is overlap-based, not matrix factorization | Add offline model training |
| Chat expansion adds latency on first unknown query | Expand offline dictionary |
| Guest users get cold-start only | Session-based anonymous profiling |
