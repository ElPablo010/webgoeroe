<?php

namespace App\Models;

/**
 * Case: het model komt uit de module webgoeroe/cases
 * (config/cases.php → model). Deze subklasse houdt App\Models\CaseStudy
 * bruikbaar voor de MCP-tools, de seeders en het eigen cases-grid-ontwerp.
 */
class CaseStudy extends \Webgoeroe\Cases\Models\CaseStudy
{
}
