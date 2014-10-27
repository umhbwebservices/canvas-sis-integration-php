#!/bin/bash
php /home/umhb/canvas/sis_import_users.php > daily-output.txt

php /home/umhb/canvas/sis_import_courses_and_terms.php >> daily-output.txt

php /home/umhb/canvas/sis_import_enrollments.php >> daily-output.txt

php /home/umhb/canvas/sis_import_enrollment_orientation.php >> daily-output.txt

php /home/umhb/canvas/sis_import_enrollment_faculty_training.php >> daily-output.txt
