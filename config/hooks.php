<?php

namespace texnixe\Similar;

return [
    'page.create:after' => function() {
        Similar::flush();
    },
    'page.update:after' => function() {
        Similar::flush();
    },
    'page.delete:after' => function() {
        Similar::flush();
    },
    'page.changeSlug:after' => function() {
        Similar::flush();
    },
    'page.changeStatus:after' => function() {
        Similar::flush();
    },
    'file.create:after' => function() {
        Similar::flush();
    },
    'file.update:after' => function() {
        Similar::flush();
    },
    'file.delete:after' => function() {
        Similar::flush();
    },
    'file.changeName:after' => function() {
        Similar::flush();
    },
    'file.replace:after' => function() {
        Similar::flush();
    }
];
