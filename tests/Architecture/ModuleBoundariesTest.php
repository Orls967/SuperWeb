<?php

// Architecture tests will be expanded in task 0.9
// For now, ensure basic conventions hold

arch('app classes use strict types')
    ->expect('App')
    ->toBeClasses();
