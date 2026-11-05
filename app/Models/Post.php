<?php

namespace App\Models;

/**
 * Blogartikel: het model komt uit de module webgoeroe/blog
 * (config/blog.php → model). Deze subklasse houdt App\Models\Post
 * bruikbaar voor de MCP-tools en de seeders.
 */
class Post extends \Webgoeroe\Blog\Models\Post
{
}
