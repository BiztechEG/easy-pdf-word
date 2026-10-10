<?php

use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;

return [
    'title' => 'Note',
    'fields' => [
        'to' => 'required|string|max:100',
        'lines' => 'required|array|min:1',
        'lines.*' => 'string',
    ],
    'prepare' => function (array $data) {
        if (in_array('forbidden', $data['lines'], true)) {
            throw new ValidationFailed(['lines' => ['A line is forbidden.']]);
        }

        return $data + ['count' => count($data['lines'])];
    },
];
