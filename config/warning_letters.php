<?php

return [

    // Private disk (storage/app/private); never public/, no storage:link needed.
    'disk' => 'local',

    'directory' => 'warning-letters',

    // Maximum upload size in kilobytes (5 MB).
    'max_kb' => (int) env('WARNING_LETTER_MAX_KB', 5120),

    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],

    // Suggestions only; the level field accepts any text up to 100 characters.
    'level_suggestions' => ['Verbal', '1st Written', '2nd Written', 'Final', 'Other'],

];
