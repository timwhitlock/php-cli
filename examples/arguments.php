<?php
/**
 * Example shows command line argument registration
 * $ php -f arguments.php -- --name=Tim
 */

require __DIR__.'/../cli.php';

cli::register_arg( 'n', 'name', 'Your name', true );
cli::validate_args();

$name = cli::arg('n');

cli::log( 'Hallo %s', $name );