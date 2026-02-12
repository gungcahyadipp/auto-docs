<?php

use Dedoc\Scramble\Scramble;

Scramble::registerUiRoute(path: 'docs')->name('scramble.docs.ui');

Scramble::registerJsonSpecificationRoute(path: 'docs/api.json')->name('scramble.docs.document');
