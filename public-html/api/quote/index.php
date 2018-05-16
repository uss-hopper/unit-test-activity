<?php

require_once(dirname(__DIR__, 3) . "/php/classes/autoload.php");
require_once(dirname(__DIR__, 3) . "/php/lib/xsrf.php");
require_once "/etc/apache2/capstone-mysql/encrypted-config.php";
use Edu\Cnm\GreatQuote\Quote;

if(session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

//prepare an empty reply
$reply = new stdClass();
$reply->status = 200;
$reply->data = null;

try {

	$pdo = connectToEncryptedMySQL("/etc/apache2/capstone-mysql/gkephart.ini");

	$method = array_key_exists("HTTP_X_HTTP_METHOD", $_SERVER) ? $_SERVER["HTTP_X_HTTP_METHOD"] : $_SERVER["REQUEST_METHOD"];

	$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
	$quoteAuthor = filter_input(INPUT_GET, "quoteAuthor", FILTER_SANITIZE_STRING);
	$quoteRating = filter_input(INPUT_GET, "quoteRating", FILTER_VALIDATE_INT);

	// make sure the id is valid for methods that require it
	if(($method === "DELETE" || $method === "PUT") && (empty($id) === true || $id < 0)) {
		throw(new InvalidArgumentException("id cannot be empty or negative", 405));
	}

	//preform the actual get method
	if($method === "GET") {

		//set the XSRF TOKEN to protect against XSRF attacks
		setXsrfCookie();

		//determine what parameters to pass to the class
		if(empty($id) === false) {
		$reply->data = Quote::getQuoteByQuoteId($pdo, $id);
		}elseif(empty($quoteAuthor) === false){
			$reply->data = Quote::getQuoteByAuthor($pdo, $quoteAuthor);
		} elseif(empty($quoteRating) === false) {
			$reply->data = Quote::getQuoteByRating($pdo, $quoteRating);
		} else {
			$reply->data = Quote::getAllQuotes($pdo);
		}
	} elseif($method === "PUT" || $method === "POST") {

		verifyXsrf();
		$requestContent = file_get_contents("php://input");
		$requestObject = json_decode($requestContent);

		// make sure VenueId is available
		if(empty($requestObject->greatQuote) === true) {
			throw(new \InvalidArgumentException ("No quote", 405));
		}

		// make sure post content is available (require field)
		if(empty($requestObject->quoteAuthor) === true) {
			throw(new \InvalidArgumentException ("No author", 405));
		}
		// make sure post created  date is accurate (optional field)
		if(empty($requestObject->quotePoster === true)) {
			$requestObject->postCreatedDate = new \DateTime();
		}
		// make sure post Event date is accurate (optional field)
		if(empty($requestObject->quoteRating) === true) {
			$requestObject->quoteRating;
		}

		if ($method === "PUT") {

			//grab the quote by its primary key to preform the actual update.
			$quote = Quote::getQuoteByQuoteId($pdo, $id);
			if ($quote === null) {
				throw (new RuntimeException("Quote does not exist", 404));
			}

			//assign quote state variables to the request object's variables
			$quote->setGreatQuote($requestObject->greatQuote);
			$quote->setQuoteAuthor($requestObject->quoteAuthor);
			$quote->setQuotePoster($requestObject->quotePoster);
			$quote->setQuoteRating($requestObject->quoteRating);

			//update the reply for the front end
			$reply->message = "quote successfully updated";
		} elseif($method === "POST") {

			//create the quote object from variables in the request object
			$quote = new Quote($requestObject->greatQuote, $requestObject->quoteAuthor, $requestObject->quotePoster, $requestObject->quoteRating);
			$quote->insert($pdo);

			//update the reply for the front end
			$reply->message = "quote created perfectly";
		}
	} else if($method === "DELETE") {
		verifyXsrf();


		//grab the quote by its primary key to preform the delete.
		$quote = Quote::getQuoteByQuoteId($pdo, $id);
		if ($quote === null) {
			throw (new RuntimeException("Quote does not exist", 404));
		}

		//delete the quote
		$quote->delete($pdo);

		//update the reply
		$reply->message = "Post deleted Ok";
	} else {
		throw (new InvalidArgumentException("Invalid HTTP method request"));
	}
} catch(Exception $exception) {
	$reply->status = $exception->getCode();
	$reply->message = $exception->getMessage();
} catch(TypeError $typeError) {
	$reply->status = $exception->getCode();
	$reply->message = $exception->getMessage();
}

header("Content-Type: application/json");
if($reply->data === null) {
	unset($reply->data);
}

// encode and return reply to front end caller
echo json_encode($reply);