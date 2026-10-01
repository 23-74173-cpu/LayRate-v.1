<?php

/*
|--------------------------------------------------------------------------
| Custom Validation Language Lines
|--------------------------------------------------------------------------
|
| Friendly, specific messages in a consistent tone: short, plain, helpful,
| no jargon. Field-specific wording comes from the "attributes" map below
| so messages read naturally ("Please add a note before saving." rather
| than "The body field is required."). Missing keys fall back to the
| framework defaults — only the tone-critical rules are overridden here.
|
*/

return [

    'required' => 'Please fill in the :attribute before continuing.',
    'email'    => 'Enter a valid email address, like name@farm.com.',
    'numeric'  => 'Enter a number for the :attribute.',
    'integer'  => 'Enter a whole number for the :attribute, like 12.',
    'min'      => [
        'string'  => 'The :attribute needs at least :min characters.',
        'numeric' => 'The :attribute must be at least :min.',
    ],
    'max'      => [
        'string'  => 'The :attribute is too long (maximum :max characters).',
        'numeric' => 'That value looks too high. Check the :attribute and try again.',
    ],
    'date'     => 'Choose a valid date for the :attribute.',
    'before_or_equal' => 'The :attribute date cannot be in the future.',
    'after_or_equal'  => 'The :attribute date looks too early. Check it and try again.',

    'custom' => [
        'body' => [
            'required' => 'Please add a note before saving.',
        ],
        'password' => [
            'required' => 'Enter your password to continue.',
            'min'      => 'Your password needs at least :min characters.',
        ],
        'egg_count' => [
            'required' => 'Enter the egg count for this slot.',
            'integer'  => 'Enter a whole number of eggs, like 12.',
        ],
    ],

    'attributes' => [
        'body'             => 'note',
        'email'            => 'email address',
        'password'         => 'password',
        'name'             => 'name',
        'log_date'         => 'date',
        'egg_count'        => 'egg count',
        'total_quantity_kg' => 'quantity',
        'cage_id'          => 'cage',
        'category'         => 'category',
    ],

];
