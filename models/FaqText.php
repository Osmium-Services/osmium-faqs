<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

/**
 * Plain-text helpers for FAQ content. Pure.
 */
class FaqText
{
    public const MAX_QUESTION_LENGTH = 500;
    public const MAX_ANSWER_LENGTH = 10000;

    /**
     * A question and its answer, trimmed and checked against the limits the admin editor applies.
     *
     * @return array{question: string, answer: string}
     * @throws \InvalidArgumentException with a message fit to show the editor
     */
    public static function cleanPair(string $question, string $answer): array
    {
        $question = \trim($question);
        $answer = \trim($answer);

        $questionMissing = $question === '';
        if ($questionMissing) throw new \InvalidArgumentException('Every answer needs a question');

        $answerMissing = $answer === '';
        if ($answerMissing) throw new \InvalidArgumentException('Every question needs an answer: ' . $question);

        $questionTooLong = \mb_strlen($question) > self::MAX_QUESTION_LENGTH;
        if ($questionTooLong) throw new \InvalidArgumentException('A question can be up to 500 characters');

        $answerTooLong = \mb_strlen($answer) > self::MAX_ANSWER_LENGTH;
        if ($answerTooLong) throw new \InvalidArgumentException('An answer can be up to 10,000 characters');

        return ['question' => $question, 'answer' => $answer];
    }

    /**
     * Escaped text with a blank line starting a new paragraph and a single line break kept as <br>
     */
    public static function html(string $answer): string
    {
        $trimmed = \trim($answer);
        $paragraphs = \preg_split('/\R{2,}/', $trimmed) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $text = \trim($paragraph);
            $isBlank = $text === '';
            if ($isBlank) continue;

            $escaped = \htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $withBreaks = \preg_replace('/\R/', '<br>', $escaped);
            $html .= '<p>' . $withBreaks . '</p>';
        }

        return $html;
    }

    /**
     * Lowercase letters, digits and single hyphens, for the slug of a list
     */
    public static function slug(string $text): string
    {
        $trimmed = \trim($text);
        $lower = \strtolower($trimmed);
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';

        return \trim($slug, '-');
    }
}
