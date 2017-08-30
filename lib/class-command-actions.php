<?php

namespace WP_Parser;

use WP_CLI;
use WP_CLI_Command;

/**
 * Converts PHPDoc markup into a template ready for import to a WordPress blog.
 */
class CommandActions extends WP_CLI_Command {

	private static $limit = 0;

	/**
	 * Generate a JSON file containing the PHPDoc markup, and save to filesystem.
	 *
	 * @synopsis <directory> [<output_file>]
	 *
	 * @param array $args
	 */
	public function actions( $args ) {
		// $type = empty( $args[0] ) ? 'constants' : $args[0]; // constants, hooks
		$directory   = realpath( $args[0] );
		$output_file = empty( $args[1] ) ? 'all-actions.sublime-completions' : $args[1];
		// $json        = $this->_get_phpdoc_data( $directory );
		$json        = $this->_sublime_get_phpdoc_data( $type, $directory );
		$result      = file_put_contents( $output_file, $json );
		WP_CLI::line();

		if ( false === $result ) {
			WP_CLI::error( sprintf( 'Problem writing %1$s bytes of data to %2$s', strlen( $json ), $output_file ) );
			exit;
		}

		WP_CLI::success( sprintf( 'Data exported to %1$s', $output_file ) );
		WP_CLI::line();
	}

	/**
	 * Generate the data from the PHPDoc markup.
	 *
	 * @param string $path   Directory or file to scan for PHPDoc
	 * @param string $format What format the data is returned in: [json|array].
	 *
	 * @return string|array
	 */
	protected function _sublime_get_phpdoc_data( $type, $path, $format = 'json' ) {
		WP_CLI::line( sprintf( 'Extracting PHPDoc from %1$s. This may take a few minutes...', $path ) );
		$is_file = is_file( $path );
		$files   = $is_file ? array( $path ) : get_wp_files( $path );
		$path    = $is_file ? dirname( $path ) : $path;

		if ( $files instanceof \WP_Error ) {
			WP_CLI::error( sprintf( 'Problem with %1$s: %2$s', $path, $files->get_error_message() ) );
			exit;
		}

		// $output = parse_files( $files, $path );
		$output = $this->sublime_parse_files( $type, $files, $path );

		// $output = array_unique( $output );
		array_unique( $output );

		if ( 'json' == $format ) {

			$data = array(
				"scope"       => "source.php - variable.other.php",
				"comment"     => "Action's a-z",
				"completions" => $output,
			);

			return json_encode( $data, JSON_PRETTY_PRINT );
			// return json_encode( $output, JSON_PRETTY_PRINT );
		}

		return $output;
	}

	/**
	 * @param array  $files
	 * @param string $root
	 *
	 * @return array
	 */
	function sublime_parse_files( $type, $files, $root ) {
		$output = array();

		foreach ( $files as $filename ) {

			if( self::$limit <= 5000000 ) {

				$file = new File_Reflector( $filename );

				$path = ltrim( substr( $filename, strlen( $root ) ), DIRECTORY_SEPARATOR );
				$file->setFilename( $path );

				$file->process();

				// Avoid 'wp-content' Directory
				if( strpos($file->getFilename(), 'wp-content') === false ) {

					// WP_CLI::line( "\n\n" );
					// WP_CLI::line( 'FILE - ' . $file->getFilename() );
					// WP_CLI::line( "______________________________________________________________" );

					// // TODO proper exporter
					// $out = array(
					// 	'file' => export_docblock( $file ),
					// 	'path' => str_replace( DIRECTORY_SEPARATOR, '/', $file->getFilename() ),
					// 	'root' => $root,
					// );

					// foreach ( $file->getIncludes() as $include ) {
					// 	$out['includes'][] = array(
					// 		'name' => $include->getName(),
					// 		'line' => $include->getLineNumber(),
					// 		'type' => $include->getType(),
					// 	);
					// }

					// 
					// 
					// Hooks
					// $ wp parser actions . "C:\Users\intel\AppData\Roaming\Sublime Text 3\Packages\WordPress Code Reference\all-hooks.sublime-completions"
					// $ wp parser actions . "all-hooks.sublime-completions"
					
					// if( 'hooks' === $type ) {
						if ( ! empty( $file->uses['hooks'] ) ) {
							// $output[] = export_hooks( $file->uses['hooks'] );
							$hooks = (array) $file->uses['hooks'];

							foreach ( $hooks as $hook ) {

								$type = $hook->getType();

								if( 'action' === $type ) {

									// WP_CLI::line( print_r( $args ) );
									// WP_CLI::line( print_r( export_docblock( $hook ) ) );
									// WP_CLI::line( '----------------------' );

									$name = sanitize_key( $hook->getName() );
									// $name = str_replace('$', '', $name);
									// $name = str_replace('-', '_', $name);
									// $name = str_replace('1', '', $name);
									WP_CLI::line( self::$limit . ' ' . $name );

									// Add hook.
									$output[] = $name;
								}

							}

							// $out['hooks'] = export_hooks( $file->uses['hooks'] );
						}
					// }


					// $output[] = $out;

					self::$limit++;
				}

			}

		}


		WP_CLI::line( "\n\nFound: " .count($output) . " actions!\n" );

		return $output;
		// return $output;
	}
}
