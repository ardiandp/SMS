<?php

namespace App\Domain\Administration;

/**
 * Sanitasi HTML pengumuman (PRD-05).
 *
 * Prinsip: daftar putih ketat. Tag script/style/iframe/object/embed dibuang
 * beserta isinya; tag di luar daftar dibuang tag-nya tapi isi teksnya
 * dipertahankan; atribut event handler (onclick, ...) dan URL javascript:
 * selalu dibuang. Hanya href aman (http/https/mailto/relatif) pada <a> yang
 * dipertahankan.
 */
class AnnouncementSanitizer
{
    /**
     * @var list<string>
     */
    private const ALLOWED_TAGS = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'a'];

    /**
     * @var list<string>
     */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');

        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->documentElement;

        if ($root === null) {
            return strip_tags($html);
        }

        $this->sanitizeNode($root);

        $clean = $dom->saveHTML($root) ?: '';

        if (str_starts_with($clean, '<div>') && str_ends_with($clean, '</div>')) {
            $clean = substr($clean, 5, -6);
        }

        return trim($clean);
    }

    private function sanitizeNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $fragment = $node->ownerDocument->createDocumentFragment();
                while ($child->firstChild !== null) {
                    $fragment->appendChild($child->firstChild);
                }
                $node->replaceChild($fragment, $child);
                $this->sanitizeNode($fragment);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->nodeName);

                if ($tag === 'a' && $name === 'href' && $this->isSafeHref($attribute->nodeValue)) {
                    continue;
                }

                $child->removeAttribute($attribute->nodeName);
            }

            $this->sanitizeNode($child);
        }
    }

    private function isSafeHref(string $href): bool
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        return in_array($scheme, ['', 'http', 'https', 'mailto'], true);
    }
}
