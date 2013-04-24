<?php

require __DIR__.'/../cli.php';


cli::log('Normal output');

cli::style( cli::FG_YELLOW );
cli::stdout("\nWoo,\nyellow!\n\n");

cli::err('Errors are red!');

