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

/*
 * CROSSWORD plugin question specification.
 *
 * @package    qtype_crossword
 * @copyright  2020 Brain station 23 ltd <>  {@link https://brainstation-23.com/}
 * @author     Brain station 23 ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once $CFG->libdir.'/questionlib.php';
require_once $CFG->dirroot.'/question/engine/lib.php';

/**
 * The matching question type class.
 *
 * @copyright  2020 Brain station 23 ltd <>  {@link https://brainstation-23.com/}
 * @author     Brain station 23 ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_crossword extends question_type
{
    /**
     * @param object $question
     * @return bool
     * @throws dml_exception
     */
    public function get_question_options($question) {
        global $DB;
        parent::get_question_options($question);
        $question->options = $DB->get_record('qtype_crossword_options',
            ['questionid' => $question->id]);
        $question->options->subquestions = $DB->get_records('qtype_crossword_subquestions',
            ['questionid' => $question->id], 'id ASC');

        return true;
    }

    /**
     * @param stdClass $fromform
     */
    public function save_defaults_for_new_questions(stdClass $fromform): void {
        parent::save_defaults_for_new_questions($fromform);
        $this->set_default_value('shuffleanswers', $fromform->shuffleanswers);
    }

    /**
     * @param object $question
     * @return bool|stdClass
     * @throws coding_exception
     * @throws dml_exception
     */
    public function save_question_options($question) {
        global $DB;
        $context = $question->context;
        $result = new stdClass();

        $oldsubquestions = $DB->get_records('qtype_crossword_subquestions',
            ['questionid' => $question->id], 'id ASC');

        // Insert all the new question & answer pairs.
        foreach ($question->subquestions as $key => $questiontext) {
            if ($questiontext['text'] == '' && trim($question->subanswers[$key]) == '') {
                continue;
            }
            if ($questiontext['text'] != '' && trim($question->subanswers[$key]) == '') {
                $result->notice = get_string('nomatchinganswer', 'qtype_crossword', $questiontext);
            }

            // Update an existing subquestion if possible.
            $subquestion = array_shift($oldsubquestions);
            if (!$subquestion) {
                $subquestion = new stdClass();
                $subquestion->questionid = $question->id;
                $subquestion->questiontext = '';
                $subquestion->answertext = '';
                $subquestion->id = $DB->insert_record('qtype_crossword_subquestions', $subquestion);
            }

            $subquestion->questiontext = $this->import_or_save_files($questiontext,
                $context, 'qtype_crossword', 'subquestion', $subquestion->id);
            $subquestion->questiontextformat = $questiontext['format'];
            $subquestion->answertext = trim($question->subanswers[$key]);

            $DB->update_record('qtype_crossword_subquestions', $subquestion);
        }

        // Delete old subquestions records.
        $fs = get_file_storage();
        foreach ($oldsubquestions as $oldsub) {
            $fs->delete_area_files($context->id, 'qtype_crossword', 'subquestion', $oldsub->id);
            $DB->delete_records('qtype_crossword_subquestions', ['id' => $oldsub->id]);
        }

        // Save the question options.
        $options = $DB->get_record('qtype_crossword_options', ['questionid' => $question->id]);
        if (!$options) {
            $options = new stdClass();
            $options->questionid = $question->id;
            $options->correctfeedback = '';
            $options->partiallycorrectfeedback = '';
            $options->incorrectfeedback = '';
            $options->id = $DB->insert_record('qtype_crossword_options', $options);
        }

        $options->shuffleanswers = $question->shuffleanswers;
        $options = $this->save_combined_feedback_helper($options, $question, $context, true);
        $DB->update_record('qtype_crossword_options', $options);

        $this->save_hints($question, true);

        if (!empty($result->notice)) {
            return $result;
        }

        return true;
    }

    /**
     * @param question_definition $question
     * @param object $questiondata
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);

        $question->shufflestems = $questiondata->options->shuffleanswers;
        $this->initialise_combined_feedback($question, $questiondata, true);

        $question->stems = [];
        $question->choices = [];
        $question->right = [];

        foreach ($questiondata->options->subquestions as $matchsub) {
            $key = array_search($matchsub->answertext, $question->choices, true);
            if ($key === false) {
                $key = $matchsub->id;
                $question->choices[$key] = $matchsub->answertext;
            }

            if ($matchsub->questiontext !== '') {
                $question->stems[$matchsub->id] = $matchsub->questiontext;
                $question->stemformat[$matchsub->id] = $matchsub->questiontextformat;
                $question->right[$matchsub->id] = $key;
            }
        }
    }

    /**
     * @param object $hint
     * @return question_hint|question_hint_with_parts
     */
    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    /**
     * @param $questionid
     * @param int $contextid
     * @throws dml_exception
     */
    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('qtype_crossword_options', ['questionid' => $questionid]);
        $DB->delete_records('qtype_crossword_subquestions', ['questionid' => $questionid]);

        parent::delete_question($questionid, $contextid);
    }

    /**
     * @param stdClass $questiondata
     * @return float|int
     */
    public function get_random_guess_score($questiondata) {
        $q = $this->make_question($questiondata);

        return 1 / count($q->choices);
    }

    /**
     * @param $questiondata
     * @return array
     */
    public function get_possible_responses($questiondata) {
        $subqs = [];

        $q = $this->make_question($questiondata);

        foreach ($q->stems as $stemid => $stem) {
            $responses = [];
            foreach ($q->choices as $choiceid => $choice) {
                $responses[$choiceid] = new question_possible_response(
                    $q->html_to_text($stem, $q->stemformat[$stemid]).': '.$choice,
                    ($choiceid == $q->right[$stemid]) / count($q->stems));
            }
            $responses[null] = question_possible_response::no_response();

            $subqs[$stemid] = $responses;
        }

        return $subqs;
    }

    /**
     * @param int $questionid
     * @param int $oldcontextid
     * @param int $newcontextid
     * @throws dml_exception
     */
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        global $DB;
        $fs = get_file_storage();

        parent::move_files($questionid, $oldcontextid, $newcontextid);

        $subquestionids = $DB->get_records_menu('qtype_crossword_subquestions',
            ['questionid' => $questionid], 'id', 'id,1');
        foreach ($subquestionids as $subquestionid => $notused) {
            $fs->move_area_files_to_new_context($oldcontextid,
                $newcontextid, 'qtype_crossword', 'subquestion', $subquestionid);
        }

        $this->move_files_in_combined_feedback($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    /**
     * @param int $questionid
     * @param int $contextid
     * @throws dml_exception
     */
    protected function delete_files($questionid, $contextid) {
        global $DB;
        $fs = get_file_storage();

        parent::delete_files($questionid, $contextid);

        $subquestionids = $DB->get_records_menu('qtype_crossword_subquestions',
            ['questionid' => $questionid], 'id', 'id,1');
        foreach ($subquestionids as $subquestionid => $notused) {
            $fs->delete_area_files($contextid, 'qtype_crossword', 'subquestion', $subquestionid);
        }

        $this->delete_files_in_combined_feedback($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }
}
