<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Interaction action weights
    |--------------------------------------------------------------------------
    | Higher weight = stronger signal of user interest for collaborative filtering.
    */
    'action_weights' => [
        'view' => 1,
        'click' => 2,
        'search_view' => 2,
        'like' => 5,
        'contact' => 8,
        'unlike' => -5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Hybrid score component weights (must sum to 1.0)
    |--------------------------------------------------------------------------
    | final_score = collaborative * w1 + content * w2 + location * w3 + popularity * w4
    */
    'score_weights' => [
        'collaborative' => 0.40,
        'content' => 0.25,
        'location' => 0.25,
        'popularity' => 0.10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Location distance buckets (km => score)
    |--------------------------------------------------------------------------
    */
    'distance_buckets' => [
        1 => 1.00,
        3 => 0.85,
        5 => 0.70,
        10 => 0.45,
        20 => 0.20,
    ],

    'distance_beyond_km_score' => 0.0,

    /*
    |--------------------------------------------------------------------------
    | Popularity formula: views_count + (likes_count * multiplier)
    |--------------------------------------------------------------------------
    */
    'popularity_likes_multiplier' => 3,

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */
    'cache_ttl' => (int) env('RECOMMENDATION_CACHE_TTL', 900),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */
    'default_limit' => 12,
    'max_candidates' => 200,
    'view_dedupe_minutes' => 60,

    /*
    |--------------------------------------------------------------------------
    | Collaborative filtering
    |--------------------------------------------------------------------------
    */
    'min_neighbor_overlap' => 1,
    'max_neighbors' => 50,

    /*
    |--------------------------------------------------------------------------
    | OpenAI semantic similarity (optional)
    |--------------------------------------------------------------------------
    | When property embeddings exist, blend semantic score into content score.
    | 0 = attributes only, 0.3 = 30% semantic + 70% attributes (recommended).
    */
    'semantic_blend_weight' => (float) env('RECOMMENDATION_SEMANTIC_BLEND', 0.30),

    'use_openai_embeddings' => (bool) env('RECOMMENDATION_USE_OPENAI', true),

];
