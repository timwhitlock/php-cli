<?php

require __DIR__.'/../cli.php';


cli::log('Normal output');

cli::style( cli::FG_GREEN );
cli::stdout("\nWoo,\ngreen!\n\n");

cli::err('Errors are red!');

