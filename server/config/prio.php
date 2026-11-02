<?php

return [

    /*
    | How many open tasks fit into each priority column. Columns without a
    | limit (P3, backlog) accept anything. When a full column receives a new
    | task, the user has to pick which occupant moves one level down.
    */
    'capacity' => [
        1 => (int) env('PRIO_P1_CAPACITY', 3),
        2 => (int) env('PRIO_P2_CAPACITY', 7),
    ],

    // Tasks due within this many days are ranked right after P1.
    'due_soon_days' => 3,

];
