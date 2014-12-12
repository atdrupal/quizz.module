# Install site
# drush si -vy testing
# drush dl quiz-7.x-5.x --package-handler=git_drupalorg
# drush en -vy local ctools field field_sql_storage file filter image node system simpletest text user entity quiz_page quiz quiz_question quiz_ddlines long_answer matching quiz_directions multichoice scale short_answer truefalse views views_bulk_operations
# drush uli admin/quiz/settings/questions_settings

echo "Config admin/quiz/settings/questions_settings…"
drush dpost admin/quiz/settings/questions_settings '{
  "long_answer_default_max_score" : "20",
  "quiz_matching_form_size": "20",
  "multichoice_def_scoring": "1",
  "quiz_ddlines_canvas_width": 777,
  "quiz_ddlines_canvas_height": "555",
  "quiz_ddlines_hotspot_radius": "11",
  "quiz_ddlines_pointer_radius": "7",
  "quiz_ddlines_feedback_correct": "DDLines feedback for correct",
  "quiz_ddlines_feedback_wrong": "DDLines feedback for incorrect",
  "scale_max_num_of_alts": "11",
  "short_answer_default_max_score": "7"
}' > /dev/null

echo "Config admin/quiz/settings/config…"
drush dpost admin/quiz/settings/config '{
  "quiz_auto_revisioning": "FALSE",
  "quiz_durod": "1",
  "quiz_default_close": 31,
  "quiz_max_result_options": 7,
  "quiz_remove_partial_quiz_record": "604800",
  "quiz_autotitle_length": "77",
  "quiz_pager_start": "77",
  "quiz_pager_siblings": "7",
  "quiz_name": "Quizz",
  "quiz_email_results": "TRUE",
  "quiz_email_results_subject_taker": "!title Results Notice from !sitename",
  "quiz_email_results_body_taker": "*****",
  "quiz_email_results_body": "*****"
}' > /dev/null

echo "Config admin/quiz/settings/quiz-form…"
drush dpost admin/quiz/settings/quiz-form '{
  "allow_resume": "FALSE",
  "allow_skipping": "1",
  "allow_jumping": "1",
  "allow_change": "FALSE",
  "backwards_navigation": "FALSE",
  "repeat_until_correct": "TRUE",
  "build_on_last": "correct",
  "mark_doubtful": "1",
  "show_passed": "FALSE",
  "randomization": "1",
  "review_options[question][quiz_question_view_full]": "quiz_question_view_full",
  "review_options[question][quiz_question_view_teaser]": "quiz_question_view_teaser",
  "review_options[question][attempt]": "attempt",
  "review_options[question][choice]": "choice",
  "review_options[question][correct]": "correct",
  "review_options[question][score]": "score",
  "review_options[question][answer_feedback]": "answer_feedback",
  "review_options[question][question_feedback]": "question_feedback",
  "review_options[question][solution]": "solution",
  "review_options[question][quiz_feedback]": "quiz_feedback",
  "review_options[end][quiz_question_view_full]": "",
  "review_options[end][quiz_question_view_teaser]": "quiz_question_view_teaser",
  "review_options[end][attempt]": "attempt",
  "review_options[end][choice]": "choice",
  "review_options[end][correct]": "correct",
  "review_options[end][score]": "score",
  "review_options[end][answer_feedback]": "answer_feedback",
  "review_options[end][question_feedback]": "question_feedback",
  "review_options[end][solution]": "solution",
  "review_options[end][quiz_feedback]": "quiz_feedback",
  "takes": "7",
  "show_attempt_stats": "FALSE",
  "keep_results": "2",
  "time_limit": "777",
  "pass_rate": "77",
  "summary_pass[value]": "*****",
  "summary_default[value]": "*****"
}' > /dev/null

echo "Creating a a demo quizzes…"
drush dpost node/add/quiz '{"title": "Quiz 1", "body[und][0][value]": "Quiz 1 body"}' > /dev/null
drush dpost node/add/quiz '{"title": "Quiz 2", "body[und][0][value]": "Quiz 2 body"}' > /dev/null
drush dpost node/add/quiz '{"title": "Quiz 3", "body[und][0][value]": "Quiz 3 body"}' > /dev/null

drush dpost node/add/quiz-ddlines '{"title": ""}' > /dev/null
drush dpost node/add/long-answer '{}' > /dev/null
drush dpost node/add/matching '{}' > /dev/null
drush dpost node/add/multichoice '{}' > /dev/null
drush dpost node/add/quiz-page '{}' > /dev/null
drush dpost node/add/scale '{}' > /dev/null
drush dpost node/add/short-answer '{}' > /dev/null
drush dpost node/add/truefalse '{}' > /dev/null

echo "Done!"
