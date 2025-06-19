<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait Searchable
{
    /**
     * Get the searchable fields.
     */
    protected function getSearchableFields(): array
    {
        return property_exists($this, 'searchable') ? $this->searchable : ['title', 'content'];
    }

    /**
     * Scope for searching.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $fields = $this->getSearchableFields();
        $searchTerms = $this->parseSearchTerms($search);

        return $query->where(function ($query) use ($fields, $searchTerms) {
            foreach ($searchTerms as $term) {
                $query->where(function ($query) use ($fields, $term) {
                    foreach ($fields as $field) {
                        if (str_contains($field, '.')) {
                            // Handle relationship fields
                            $parts = explode('.', $field);
                            $relation = $parts[0];
                            $relationField = $parts[1];
                            
                            $query->orWhereHas($relation, function ($query) use ($relationField, $term) {
                                $query->where($relationField, 'LIKE', "%{$term}%");
                            });
                        } else {
                            // Handle direct fields
                            $query->orWhere($field, 'LIKE', "%{$term}%");
                        }
                    }
                });
            }
        });
    }

    /**
     * Scope for full text search.
     */
    public function scopeFullTextSearch(Builder $query, string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $fields = $this->getSearchableFields();
        $searchableColumns = implode(',', $fields);

        return $query->whereRaw(
            "MATCH({$searchableColumns}) AGAINST(? IN BOOLEAN MODE)",
            [$search]
        );
    }

    /**
     * Parse search terms.
     */
    protected function parseSearchTerms(string $search): array
    {
        // Remove extra spaces and split by space
        $terms = explode(' ', trim(preg_replace('/\s+/', ' ', $search)));
        
        // Remove empty terms and return
        return array_filter($terms, function ($term) {
            return !empty(trim($term));
        });
    }

    /**
     * Highlight search terms in text.
     */
    public function highlightSearchTerms(string $text, string $search, string $highlightClass = 'highlight'): string
    {
        if (empty($search)) {
            return $text;
        }

        $terms = $this->parseSearchTerms($search);
        
        foreach ($terms as $term) {
            $text = preg_replace(
                '/(' . preg_quote($term, '/') . ')/i',
                '<span class="' . $highlightClass . '">$1</span>',
                $text
            );
        }

        return $text;
    }

    /**
     * Get search excerpt.
     */
    public function getSearchExcerpt(string $field, string $search, int $length = 200): string
    {
        $text = $this->{$field};
        
        if (empty($search)) {
            return Str::limit(strip_tags($text), $length);
        }

        $terms = $this->parseSearchTerms($search);
        $firstTerm = $terms[0] ?? '';
        
        // Find position of first search term
        $pos = stripos($text, $firstTerm);
        
        if ($pos !== false) {
            // Extract text around the search term
            $start = max(0, $pos - $length / 2);
            $excerpt = substr($text, $start, $length);
            
            // Clean up excerpt
            $excerpt = strip_tags($excerpt);
            
            if ($start > 0) {
                $excerpt = '...' . $excerpt;
            }
            
            if (strlen($text) > $start + $length) {
                $excerpt .= '...';
            }
            
            return $this->highlightSearchTerms($excerpt, $search);
        }

        return Str::limit(strip_tags($text), $length);
    }
}