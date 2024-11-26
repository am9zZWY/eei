<?php
require_once 'config.php';
require_once 'utils.php';
require_once 'i18n/i18n.php';
require_once 'event_type.php';

global $i18n, $events, $CONFIG_TERM, $FILE_REVISION, $CONFIG_CONTACT;

// Redirect to main page if event ID is not provided or invalid.
const REDIRECT_URL = 'Location:/';
$eventId = filter_input(INPUT_GET, 'e', FILTER_SANITIZE_ENCODED);
if (!$eventId || !isset($events[$eventId])) {
	header(REDIRECT_URL);
	exit;
}

$event = $events[$eventId];
$registrationId = filter_input(INPUT_GET, 'r', FILTER_SANITIZE_ENCODED);

// Handle POST request, e.g. unsubscribe or register
renderPage($event, $registrationId);

/**
 * Handles the POST request
 * Example: Unsubscribe from an event or register for an event
 * @param mixed $event
 * @param mixed $registrationId
 * @return void
 */
function handlePostRequest($event, $registrationId)
{
	global $i18n;

	if ($registrationId) {
		$result = deleteRegistration($registrationId, $event);
		renderMessageBlock($result[0], $result[1]);
	} else {
		$result = register($event);
		renderMessageBlock($result[0], $result[1]);
	}
}

/**
 * Renders a message block
 * Example: Success or error message
 * @param mixed $isSuccess
 * @param mixed $message
 * @return void
 */
function renderMessageBlock($isSuccess, $message)
{
	$class = $isSuccess ? 'info' : 'error';
	echo "<div class=\"text-block $class\">$message</div>";
}

/**
 * Renders the complete page
 * Example: Event details, registration form, error messages
 * @param mixed $event
 * @param mixed $registrationId
 * @return void
 */
function renderPage($event, $registrationId)
{
	global $i18n, $CONFIG_TERM, $FILE_REVISION, $CONFIG_CONTACT;

	$language = htmlspecialchars($i18n->getLanguage(), ENT_QUOTES);
	?>
	<!DOCTYPE html>
	<html lang="<?= $language ?>">

	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta http-equiv="X-UA-Compatible" content="ie=edge">
		<link rel="stylesheet" href="css/style.css<?= $FILE_REVISION; ?>">
		<link rel="stylesheet" href="css/input.css<?= $FILE_REVISION; ?>">
		<link rel="stylesheet" href="css/icons.css<?= $FILE_REVISION; ?>">
		<title><?= htmlspecialchars($event->name) . " - " . $CONFIG_TERM ?></title>
	</head>

	<body>
		<div id="center" class="small">
			<h1><?= htmlspecialchars($event->name) . " - " . $CONFIG_TERM ?></h1>
			<h2 class="description icon clock"><?= htmlspecialchars($event->getEventDateString()) ?></h2>
			<h2 class="description icon marker"><?= htmlspecialchars($event->location) ?></h2>
			<h2 class="description"><?= $i18n['remaining'] . ": " . htmlspecialchars($event->getRemainingSpots()) ?></h2>
			<?php if ($event->dinosAllowed): ?>
				<h4 class="description"><?= $i18n['dinos'] ?></h4>
			<?php endif; ?>
			<?= $event->text ?>
			<?php if (!empty($event->info)): ?>
				<div class="text-block info"><?= $event->info ?></div>
			<?php endif; ?>
			<div class="block">
				<?php
				if ($_SERVER['REQUEST_METHOD'] === 'POST') {
					handlePostRequest($event, $registrationId);
				} else {
					if ($registrationId) {
						renderUnsubscribeForm($event, $registrationId);
					} elseif ($event->canRegister()) {
						renderRegistrationForm($event);
					} else {
						renderErrorMessages($event);
					}
				}
				?>
				<div class="container">
					<a href="index.php?lang=<?= $language ?>">
						<div class="link"><?= $i18n['back'] ?></div>
					</a>
					<a href="calendar.php?e=<?= htmlspecialchars($event->link) ?>&lang=<?= $language ?>&ics">
						<div class="link color-border"><?= $i18n['calendar_download'] ?></div>
					</a>
				</div>
			</div>
		</div>
	</body>

	</html>
	<?php
}

/**
 * Renders the unsubscribe form
 * Example: Unsubscribe from an event
 * @param mixed $event
 * @return array
 */
function renderUnsubscribeForm($event, $registrationId)
{
	global $i18n;

	?>
	<form action="event.php?e=<?= htmlspecialchars($event->link) ?>&r=<?= htmlspecialchars($registrationId) ?>"
		method="post">
		<div class="text-block"><?= $i18n->translate('unsubscribe_text') ?></div>
		<input type="hidden" name="registration_id" value="<?= htmlspecialchars($registrationId) ?>">
		<input type="submit" name="delete_registration" value="<?= $i18n->translate('unsubscribe') ?>">
	</form>
	<?php
}

function renderRegistrationForm($event)
{
	global $i18n;
	?>
	<form method="post" action="#">
		<label for="form-name"><?= $i18n['form_yourName'] ?>:<br>
			<input type="text" id="form-name" name="name" required size="30">
		</label><br><br>
		<label for="form-mail"><?= $i18n['form_email'] ?>:<br>
			<input type="email" id="form-mail" name="mail" required size="30">
		</label><br><br>
		<?php
		renderCourseOptions($event);
		renderFoodOptions($event);
		renderFoodBreakfastOptions($event);
		?>
		<script src="js/saveFormValues.js"></script>
		<input type="submit" value="<?= $i18n['send'] ?>" onclick="saveFormValues()">
	</form>
	<?php
}

function renderCourseOptions($event)
{
	if (!$event->form['course_required']) {
		return;
	}

	global $i18n;

	echo $i18n['form_study_programme'] . ':<br>';
	$courses = [
		['Informatik', 'form_cs'],
		['Lehramt', 'form_cs_ed'],
		['Bioinformatik', 'form_cs_bio'],
		['Medizininformatik', 'form_cs_med'],
		['Medieninformatik', 'form_cs_media'],
		['Maschinelles Lernen', 'form_ml'],
		['Kognitionswissenschaft', 'form_cog'],
		['Nebenfach', 'form_subsidiary']
	];
	foreach ($courses as $course) {
		?>
		<label>
			<input type="radio" class="form-studiengang" name="studiengang" value="<?= $course[0] ?>" required>
			<?= $i18n[$course[1]] ?>
		</label>
		<br>
	<?php } ?>
	
	<br><?= $i18n['form_degree'] ?>:<br>
	<?php

	// Degree
	$degrees = ['Bachelor', 'Master'];
	foreach ($degrees as $degree) {
		?>
		<label>
			<input type="radio" class="form-abschluss" name="abschluss" value="<?= $degree ?>" required>
			<?= $degree ?>
		</label>
		<br>
	<?php } ?>

	<br>Semester: <br>
	<?php
	// Semester
	$semesters = ['1', '2', '3', $i18n['form_many']];
	foreach ($semesters as $semester) {
		?>
		<label>
			<input type="radio" class="form-semester" name="semester" value="<?= $semester ?>" required>
			<?= $semester ?>
		</label>
		<br>
	<?php } ?>
	<br>
	<?php
}

function renderFoodOptions($event)
{
	if (!$event->form['food']) {
		return;
	}

	global $i18n;

	echo $i18n['form_food'] . ':<br>';
	$foodPreferences = [
		$i18n['form_food_no_preference'],
		$i18n['form_food_vegetarian'],
		$i18n['form_food_vegan'],
		$i18n['form_food_no_pork']
	];
	foreach ($foodPreferences as $preference) {
		?>
		<label>
			<input type="radio" class="form-essen" name="essen" value="<?= $preference ?>" required>
			<?= $preference ?>
		</label>
		<br>
	<?php } ?>
	<br>
	<?php
}

function renderFoodBreakfastOptions($event)
{
	if (!$event->form['breakfast']) {
		return;
	}

	global $i18n;
	echo $i18n['form_breakfast'] . ':<br>';
	$foodPreferences = [
		$i18n['form_food_no_preference'],
		$i18n['form_food_sweet'],
		$i18n['form_food_salty'],
	];

	foreach ($foodPreferences as $preference) {
		?>
		<label>
			<input type="radio" class="form-fruehstueck" name="fruestueck" value="<?= $preference ?>" required>
			<?= $preference ?>
		</label>
		<br>
	<?php } ?>
	<br>
	<?php
}


function renderErrorMessages($event)
{
	global $i18n, $CONFIG_CONTACT;

	echo "<div class=\"text-block error\">";
	if ($event->cancelled) {
		echo $i18n->translate('event_cancelled', ['EVENT_NAME' => $event->name, 'EMAIL_CONTACT' => $CONFIG_CONTACT]);
	} elseif ($event->getRemainingSpots() == 0) {
		echo $i18n['event_full'];
	} elseif (!$event->isUpcoming()) {
		echo $i18n['event_is_past'];
	} elseif (time() < $event->getRegistrationStartUTS()) {
		echo $i18n->translate('start_of_registration', ['REGISTRATION_DATE' => $event->getRegistrationDateString()]);
	} elseif (time() > $event->getRegistrationEndUTS()) {
		echo $i18n['end_of_registration'];
	}
	echo "</div>";
}
