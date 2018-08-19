<?php

if ( ! file_exists( 'vendor/autoload.php' ) ) return;
require_once 'vendor/autoload.php';

use CHH\Optparse;
use Colors\Color;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load( __DIR__ . '/.env' );

$c = new Color();
$parser = new Optparse\Parser();

function usage_and_exit() {
	global $parser;
	fwrite( STDERR, "{$parser->usage()}\n" );
	exit( 1 );
}

$parser->addFlag( 'dry-run', [ 'alias' => '-dr' ] );
$parser->addFlag( 'from', [ 'alias' => '-f', 'has_value' => true ] );
$parser->addFlag( 'receivers', [ 'alias' => '-r', 'has_value' => true ] );
$parser->addArgument('message', [ 'required' => true ] );

try {
	$parser->parse();
} catch ( Optparse\Exception $e ) {
	usage_and_exit();
}

$receivers = explode( ',', $parser['receivers'] );
$receivers = array_map( function ( $receiver ) {
	return [ 'msisdn' => $receiver ];
}, $receivers );

$stack = \GuzzleHttp\HandlerStack::create();
$oauth_middleware = new \GuzzleHttp\Subscriber\Oauth\Oauth1( [
	'consumer_key'    => getenv( 'consumer_key' ),
	'consumer_secret' => getenv ('consumer_secret' ),
	'token'           => '',
	'token_secret'    => ''
] );
$stack->push( $oauth_middleware );

$client = new \GuzzleHttp\Client( [
	'base_uri' => 'https://gatewayapi.com/rest/',
	'handler'  => $stack,
	'auth'     => 'oauth'
] );

$req = [
	'sender'     => $parser['from'],
	'message'    => $parser['message'],
	'recipients' => $receivers,
];

if ( $parser['dry-run'] ) {
	echo $c( 'Running dry-run!' ) . PHP_EOL;
	print_r($req);
	return;
}

$res = $client->post( 'mtsms', [ 'json' => $req ] );

if ( $res->getStatusCode() === 200 ) {
	echo $c( 'Message sent!' )->green() . PHP_EOL;
} else {
	echo $c( 'Message not sent.' )->red() . PHP_EOL;
}
