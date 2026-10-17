<?php

return [
    'modules_enabled' => env('DEMO_MODULES_ENABLED', false),
    'contact_directory_enabled' => env('DEMO_CONTACT_DIRECTORY_ENABLED', false),
    'contact_attachments_strict_inspection' => env('DEMO_CONTACT_ATTACHMENTS_STRICT_INSPECTION', false),
    'blog_enabled' => env('DEMO_BLOG_ENABLED', false),
    'blog_cover_images_strict_inspection' => env('DEMO_BLOG_COVER_IMAGES_STRICT_INSPECTION', false),
    'blog_inline_images_strict_inspection' => env('DEMO_BLOG_INLINE_IMAGES_STRICT_INSPECTION', false),
    'community_feed_enabled' => env('DEMO_COMMUNITY_FEED_ENABLED', false),
    'community_post_media_strict_inspection' => env('DEMO_COMMUNITY_POST_MEDIA_STRICT_INSPECTION', false),
];
