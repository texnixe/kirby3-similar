<?php

namespace texnixe\Similar;

return [
    'page.update:after' => function() {
        Similar::flush();
    },
    'page.create:after' => function() {
        Similar::flush();
    },
    'file.create:after' => function() {
        Similar::flush();
    },
    'page.update:after' => function() {
        Similar::flush();
    }
];
