Quiz.module [![Build Status](https://travis-ci.org/atdrupal/quiz.module.svg?branch=7.x-6.x)](https://travis-ci.org/atdrupal/quiz.module) [![Gitter chat](https://badges.gitter.im/atdrupal/quiz.module.png)](https://gitter.im/atdrupal/quiz.module)
====

Overview
--------
The quiz.module is a framework which allows you to create interactive quizzes 
for your visitors. It allows for the creation of questions of varying types, and
to collect those questions into quizzes. 

Requirements
------------

The module consists of two types of modules: the Quiz module itself 
(quiz.module), and various question types (example: multichoice.module). The 
main Quiz module, the Quiz Question module and at least one question type module 
are required to be both installed and enabled for this module to function properly.

Features
--------
This list isn't complete (not even close)

 - Administrative features:
    o Assign feedback to responses to help point out places for further study
    o Supports multiple answers to quiz questions (on supporting question types)
    o Limit the number of takes users are allowed
    o Extensibility allows for additional question types to be added
    o Permissions (create/edit)
    o Randomize questions during the quiz
    o Assign only specific questions from the question bank

 - User features:
   o Can create/edit own quizzes.
   o Can take a quiz if have 'view quizzes' permissions, and receive score

Credits
-------
- Specification:      Robert Douglass
- Original author:    Károly Négyesi
- Update to Drupal 5: Wim Mostrey & riverfr0zen
