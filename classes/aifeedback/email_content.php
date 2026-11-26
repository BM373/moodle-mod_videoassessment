<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_videoassessment\aifeedback;

/**
 * The message a learner gets with the transcript of a submitted video.
 *
 * The transcript comes first, as it is what the learner asked for; the
 * AI's comments follow when there are any. Strings are read in the current
 * language, so the caller switches to the learner's language first.
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class email_content {
    /**
     * Subject, plain text and HTML of the message.
     *
     * @param \stdClass $attempt The processed attempt (transcript, status, feedback JSON).
     * @param \stdClass $info activity and course (formatted names) and url (of the AI feedback page).
     * @return \stdClass subject, text, html.
     */
    public static function build(\stdClass $attempt, \stdClass $info): \stdClass {
        $done = (int)$attempt->status === attempt_manager::STATUS_DONE;
        $feedback = $done ? (json_decode((string)$attempt->feedback, true) ?: []) : [];
        $sep = get_string('labelsep', 'langconfig');
        $transcript = trim((string)$attempt->transcript);

        $text = [get_string('aifb_email_intro', 'videoassessment', $info), ''];
        $html = [\html_writer::tag('p', s(get_string('aifb_email_intro', 'videoassessment', $info)))];

        $text[] = get_string('aifb_transcript', 'videoassessment');
        $text[] = $transcript;
        $text[] = '';
        $html[] = \html_writer::tag('h3', s(get_string('aifb_transcript', 'videoassessment')));
        $html[] = \html_writer::tag('blockquote', nl2br(s($transcript)), [
            'style' => 'margin: 0 0 1em; padding: 0.5em 1em; border-left: 4px solid #ccc;',
        ]);

        if (!$done) {
            $text[] = get_string('aifb_email_nofeedback', 'videoassessment');
            $text[] = $info->url;
            $html[] = \html_writer::tag('p', s(get_string('aifb_email_nofeedback', 'videoassessment')) . ' '
                . \html_writer::link($info->url, s(get_string('aifeedback', 'videoassessment'))));
            return self::finish($info, $text, $html);
        }

        $summary = trim((string)($feedback['summary'] ?? ''));
        if ($summary !== '') {
            $label = get_string('aifb_summary', 'videoassessment');
            $text[] = $label . $sep . $summary;
            $text[] = '';
            $html[] = \html_writer::tag('p', \html_writer::tag('strong', s($label)) . s($sep) . s($summary));
        }

        foreach (['strengths' => 'aifb_strengths', 'improvements' => 'aifb_improvements'] as $key => $heading) {
            $items = array_filter($feedback[$key] ?? [], 'is_array');
            if (!$items) {
                continue;
            }
            $text[] = get_string($heading, 'videoassessment');
            $lines = [];
            foreach ($items as $item) {
                [$line, $linehtml] = self::item($item, $sep);
                $text[] = '- ' . $line;
                $lines[] = \html_writer::tag('li', $linehtml);
            }
            $text[] = '';
            $html[] = \html_writer::tag('h3', s(get_string($heading, 'videoassessment')));
            $html[] = \html_writer::tag('ul', implode('', $lines));
        }

        $keywords = array_filter($feedback['keywords'] ?? [], fn($k) => is_array($k) && isset($k['keyword']));
        if ($keywords) {
            $text[] = get_string('aifb_keywords', 'videoassessment');
            $lines = [];
            foreach ($keywords as $keyword) {
                $state = get_string(!empty($keyword['used']) ? 'aifb_used' : 'aifb_notused', 'videoassessment');
                $text[] = '- ' . $keyword['keyword'] . $sep . $state;
                $lines[] = \html_writer::tag('li', s($keyword['keyword']) . s($sep) . s($state));
            }
            $text[] = '';
            $html[] = \html_writer::tag('h3', s(get_string('aifb_keywords', 'videoassessment')));
            $html[] = \html_writer::tag('ul', implode('', $lines));
        }

        $nextstep = trim((string)($feedback['nextstep'] ?? ''));
        if ($nextstep !== '') {
            $label = get_string('aifb_nextstep', 'videoassessment');
            $text[] = $label . ' ' . $nextstep;
            $text[] = '';
            $html[] = \html_writer::tag('p', \html_writer::tag('strong', s($label)) . ' ' . s($nextstep));
        }

        $text[] = get_string('aifb_email_link', 'videoassessment');
        $text[] = $info->url;
        $html[] = \html_writer::tag('p', s(get_string('aifb_email_link', 'videoassessment')) . ' '
            . \html_writer::link($info->url, s(get_string('aifeedback', 'videoassessment'))));
        return self::finish($info, $text, $html);
    }

    /**
     * One strength or improvement, as text and HTML.
     *
     * @param array $item quote, comment and, for improvements, suggestion.
     * @param string $sep Label separator of the language.
     * @return string[] [text, html]
     */
    private static function item(array $item, string $sep): array {
        $quote = trim((string)($item['quote'] ?? ''));
        $comment = trim((string)($item['comment'] ?? ''));
        $suggestion = trim((string)($item['suggestion'] ?? ''));
        $quoted = $quote !== '' ? get_string('aifb_email_quote', 'videoassessment', $quote) : '';
        $text = $quoted !== '' ? $quoted . $sep : '';
        $html = $quoted !== '' ? \html_writer::tag('em', s($quoted)) . s($sep) : '';
        $text .= $comment;
        $html .= s($comment);
        if ($suggestion !== '') {
            $try = get_string('aifb_suggestion', 'videoassessment');
            $text .= ' ' . $try . ' ' . $suggestion;
            $html .= ' ' . \html_writer::tag('strong', s($try)) . ' ' . s($suggestion);
        }
        return [$text, $html];
    }

    /**
     * Add the disclaimer and put the parts together.
     *
     * @param \stdClass $info Activity information.
     * @param string[] $text Lines of the plain text.
     * @param string[] $html Blocks of the HTML.
     * @return \stdClass subject, text, html.
     */
    private static function finish(\stdClass $info, array $text, array $html): \stdClass {
        $disclaimer = get_string('aifb_disclaimer', 'videoassessment');
        $text[] = '';
        $text[] = $disclaimer;
        $html[] = \html_writer::tag('p', \html_writer::tag('small', s($disclaimer)));
        return (object)[
            'subject' => get_string('aifb_email_subject', 'videoassessment', $info->activity),
            'text' => implode("\n", $text),
            'html' => implode("\n", $html),
        ];
    }
}
