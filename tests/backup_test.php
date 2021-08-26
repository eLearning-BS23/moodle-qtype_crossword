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

/**
 * Tests for the crossword question type backup and restore logic.
 *
 * @package    qtype_crossword
 * @copyright  2021 Brain station 23 ltd.
 * @author     Brain station 23 ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/externallib.php');


/**
 * Tests for the crossword question type backup and restore logic.
 */
class qtype_crossword_backup_testcase extends advanced_testcase
{

    /**
     * Duplicate quiz with a crossword question, and check it worked.
     */
    public function test_duplicate_crossword_question()
    {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $coregenerator = $this->getDataGenerator();
        $questiongenerator = $coregenerator->get_plugin_generator('core_question');

        // Create a course with a page that embeds a question.
        $course = $coregenerator->create_course();
        $quiz = $coregenerator->create_module('quiz', ['course' => $course->id]);
        $quizcontext = context_module::instance($quiz->cmid);

        $cat = $questiongenerator->create_question_category(['contextid' => $quizcontext->id]);
        $question = $questiongenerator->create_question('crossword', 'trickynums', ['category' => $cat->id]);

        // Store some counts.
        $numquizzes = count(get_fast_modinfo($course)->instances['quiz']);
        $numcrosswordquestions = $DB->count_records('question', ['qtype' => 'crossword']);

        // Duplicate the page.
        duplicate_module($course, get_fast_modinfo($course)->get_cm($quiz->cmid));

        // Verify the copied quiz exists.
        $this->assertCount($numquizzes + 1, get_fast_modinfo($course)->instances['quiz']);

        // Verify the copied question.
        $this->assertEquals($numcrosswordquestions + 1, $DB->count_records('question', ['qtype' => 'crossword']));
        $newcrosswordid = $DB->get_field_sql("
                SELECT MAX(id)
                  FROM {question}
                 WHERE qtype = ?
                ", ['crossword']);
        $crossworddata = question_bank::load_question_data($newcrosswordid);

        $subquestions = array_values($crossworddata->options->subquestions);

        $this->assertSame('System.out.println(0);', $subquestions[0]->questiontext);
        $this->assertSame('0', $subquestions[0]->answertext);

        $this->assertSame('System.out.println(0.0);', $subquestions[1]->questiontext);
        $this->assertSame('0.0', $subquestions[1]->answertext);

        $this->assertSame('', $subquestions[2]->questiontext);
        $this->assertSame('NULL', $subquestions[2]->answertext);
    }
}
