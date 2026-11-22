<?php

return [
    /*
     * These values deliberately match the vocabulary already used by farmer
     * farm records. The label is for staff; the value is what eligibility
     * matching compares against.
     */
    'eligibility' => [
        'activities' => [
            'rice' => 'Rice / palay farming',
            'vegetables' => 'Vegetable growing',
            'corn' => 'Corn farming',
            'coconut' => 'Coconut farming',
            'fruit' => 'Fruit growing',
            'poultry' => 'Poultry raising',
            'livestock' => 'Livestock raising',
            'swine' => 'Swine raising',
            'cattle' => 'Cattle raising',
            'goat' => 'Goat raising',
            'fishing' => 'Fishing',
            'aquaculture' => 'Aquaculture',
        ],
        'farm_categories' => [
            'crop' => 'Crop farm',
            'food_crop' => 'Food-crop farm',
            'irrigated' => 'Irrigated farm',
            'livestock' => 'Livestock farm',
            'poultry' => 'Poultry farm',
            'fishery' => 'Fishery',
            'aquaculture' => 'Aquaculture',
            'mixed' => 'Mixed farm',
            'orchard' => 'Orchard',
            'nursery' => 'Plant nursery',
        ],
    ],
];
