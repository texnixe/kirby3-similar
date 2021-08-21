<?php

namespace texnixe\Similar;

return [
    'page.*:after' => function () {
        Similar::flush();
    },
    'file.*:after' => function () {
        Similar::flush();
    },
];
