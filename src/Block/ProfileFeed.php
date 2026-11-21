<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

/**
 * The stream of a profile as a feed, for a reader: a JSON Feed (1.1) or an RSS (2.0) one, made from what the page of the profile
 * shows (ProfileBlock), newest first.
 */
final class ProfileFeed
{
    /**
     * @param array<string,mixed> $profile ProfileBlock::data()
     * @return array<string,mixed>
     */
    public static function json(array $profile, string $page): array
    {
        return [
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => (string) $profile['title'],
            'home_page_url' => $page,
            'items' => array_map(static fn(array $post): array => [
                'id' => $post['id'],
                'url' => "$page#profile-post-{$post['id']}",
                'content_text' => $post['text'],
                'date_published' => date('c', $post['created']),
            ], (array) ($profile['feed'] ?? [])),
        ];
    }

    /** @param array<string,mixed> $profile ProfileBlock::data() */
    public static function rss(array $profile, string $page): string
    {
        $xml = static fn(string $text): string => htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $items = '';
        foreach ((array) ($profile['feed'] ?? []) as $post) {
            $items .= '<item><guid isPermaLink="false">' . $xml($post['id']) . '</guid><link>' . $xml("$page#profile-post-{$post['id']}") . '</link>'
                . '<description>' . $xml($post['text']) . '</description><pubDate>' . date(DATE_RSS, $post['created']) . '</pubDate></item>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0"><channel><title>' . $xml((string) $profile['title']) . '</title>'
            . '<link>' . $xml($page) . '</link><description>' . $xml((string) $profile['title']) . '</description>' . $items . '</channel></rss>' . "\n";
    }
}
