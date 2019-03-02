![GitHub release](https://img.shields.io/github/release/texnixe/kirby3-similar.svg?maxAge=1800) ![License](https://img.shields.io/github/license/mashape/apistatus.svg) ![Kirby 3 Pluginkit](https://img.shields.io/badge/Pluginkit-YES-cca000.svg)

# Kirby Similar

Kirby 3 Similar is a [Kirby CMS](https://getkirby.com) plugin that lets you find items related to the current item based on similarity between fields. For each given field, the plugin calculates the Jaccard Index and then weighs all indices based on the factor for each field.

Example use case:
The current page has a tags field with three values (red, green, blue). You want to find all sibling pages with a minimum Jaccard Index of 0.3.

## Commercial Usage

This plugin is free but if you use it in a commercial project please consider

- [making a donation](https://www.paypal.me/texnixe/10) or
- [buying a Kirby license using this affiliate link](https://a.paddle.com/v2/click/1129/38380?link=1170)

## How is it different from the Kirby 3 Related plugin

- It allows you to pass multiple fields as an array with a factor for each field, depending on the importance of this field for determining the similarity.
- The similarity is calculated according to the Jaccard Index, rather than by the number of matches as in the Kirby 3 Related plugin.

A quick example that describes the difference:

**Example 1:**

Page A: blue, green
Page B: blue, green

Matches: 2
Jaccard Index: 2/2 = 1

**Example 2:**

Page A: blue, green, yellow
Page B: blue, green

Matches: 2
Jaccard Index: 2/3 = 0.66666

While both pages have the same number of matches, the Jaccard Index is lower in the second example, because the number of unique tags is taken into account as well.


## Installation

### Download

[Download the files](https://github.com/texnixe/kirby3-related/archive/master.zip) and place them inside `site/plugins/kirby-similar`.

### Git Submodule
You can add the plugin as a Git submodule.

    $ cd your/project/root
    $ git submodule add https://github.com/texnixe/kirby3-similar.git site/plugins/kirby-similar
    $ git submodule update --init --recursive
    $ git commit -am "Add Kirby Similar plugin"

Run these commands to update the plugin:

    $ cd your/project/root
    $ git submodule foreach git checkout master
    $ git submodule foreach git pull
    $ git commit -am "Update submodules"
    $ git submodule update --init --recursive


## Usage

### Similar pages
```
<?php

$similarPages = $page->similar($options);

foreach($similarPages as $p) {
  echo $p->title();
}

```

### Similar files

```
<?php

$similarImages = $image->similar($options);

foreach($similarImages as $image) {
  echo $image->filename();
}

```

### Options

You can pass an array of options:

```
<?php
$similarPages = $page->similar([
  'index' => $page->siblings(false)->visible(),
  'fields'         => 'tags',
  'threshold'      => 0.2,
  'delimiter'      => ',',
  'languageFilter' => false
]);
?>
```
#### index

The collection to search in.
Default: `$item->siblings()`

#### fields

The name of the field to search in.
Default: tags

You can pass either a single field as string, or an array of fields with a factor that serves as multiplier:

```
fields => ['tags' => 1, 'size' => 1.5, 'category' => 3]
```

You can change the factor to get better results when filtering collections. For example, if the above is your standard field setting, you might want to change it when a filter paramter is set to size:

```
fields => ['tags' => 0.5, 'size' => 2, 'category' => 3]
```

**Note that the factor is required when using multiple fields, even if you want to set the factor to the same value for each field. You can't set a simple array of fields.**

#### delimiter

The delimiter that you use to separate values in a field
Default: ,

#### threshold

The minimum Jaccard Index, i.e. a value between 0 (no similarity) and 1 (full similarity)
Default: 0.1

#### languageFilter

Filter similar items by language in a multi-language installation.
Default: false


## License

Kirby 3 Similar is open-sourced software licensed under the MIT license.

Copyright © 2019 Sonja Broda info@texniq.de https://sonjabroda.com
