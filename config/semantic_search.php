<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Semantic (AI) search
    |--------------------------------------------------------------------------
    | Uses OpenAI embeddings to match natural-language queries against properties.
    | Property vectors are pre-generated; only the search query is embedded at runtime.
    */

    'enabled' => (bool) env('SEMANTIC_SEARCH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-activate when the user types in the search box (location field)
    | while sort is "Recommended". Set false to require sort = Semantic (AI).
    |--------------------------------------------------------------------------
    */
    'auto_when_query' => (bool) env('SEMANTIC_SEARCH_AUTO', true),

    /*
    |--------------------------------------------------------------------------
    | Minimum cosine similarity (0–1) to include a property in semantic results.
    |--------------------------------------------------------------------------
    */
    'min_similarity' => (float) env('SEMANTIC_SEARCH_MIN_SIMILARITY', 0.25),

    /*
    |--------------------------------------------------------------------------
    | Cache embedded search queries to avoid repeated OpenAI calls.
    |--------------------------------------------------------------------------
    */
    'query_cache_ttl' => (int) env('SEMANTIC_SEARCH_QUERY_CACHE_TTL', 3600),

    'max_candidates' => (int) env('SEMANTIC_SEARCH_MAX_CANDIDATES', 500),

    /*
    |--------------------------------------------------------------------------
    | Arabic ↔ English query expansion
    |--------------------------------------------------------------------------
    */
    'use_ai_translation' => (bool) env('SEMANTIC_SEARCH_USE_AI_TRANSLATION', true),

    'keyword_weight' => (float) env('SEMANTIC_SEARCH_KEYWORD_WEIGHT', 0.60),

    'semantic_weight' => (float) env('SEMANTIC_SEARCH_SEMANTIC_WEIGHT', 0.40),

    /*
    |--------------------------------------------------------------------------
    | Fuzzy matching (typos / partial Arabic queries e.g. بعلب → بعلبك)
    |--------------------------------------------------------------------------
    */
    'fuzzy_match_enabled' => (bool) env('SEMANTIC_SEARCH_FUZZY', true),

    'fuzzy_min_similarity' => (float) env('SEMANTIC_SEARCH_FUZZY_MIN', 0.72),

    /*
    |--------------------------------------------------------------------------
    | Distance sorting — after relevance, nearest properties first
    |--------------------------------------------------------------------------
    */
    'sort_by_distance' => (bool) env('SEMANTIC_SEARCH_SORT_BY_DISTANCE', true),

    'include_nearby_properties' => (bool) env('SEMANTIC_SEARCH_INCLUDE_NEARBY', true),

    'nearby_radius_km' => (float) env('SEMANTIC_SEARCH_NEARBY_RADIUS_KM', 35),

];
