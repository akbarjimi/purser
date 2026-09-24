<?php

return [
    'Sheet1' => [
        'mapping' => [
            'name' => 'A1',
            'email' => 'B1',
            'age' => 'C1',
        ],
        'transformer' => '\\App\\Transformers\\SampleTransformer',
        'validation' => [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'required|integer|min:18',
        ],
    ],
];
