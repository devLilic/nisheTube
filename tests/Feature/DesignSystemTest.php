<?php

namespace Tests\Feature;

use Tests\TestCase;

class DesignSystemTest extends TestCase
{
    public function test_design_system_showcase_is_not_registered_outside_local_development(): void
    {
        $this->get('/design-system')->assertNotFound();
    }
}
