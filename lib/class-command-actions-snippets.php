<?php

namespace WP_Parser;

use WP_CLI;
use WP_CLI_Command;

/**
 * Converts PHPDoc markup into a template ready for import to a WordPress blog.
 */
class CommandActionsSnippets extends WP_CLI_Command {

	private static $limit = 0;

	/**
	 * Generate a JSON file containing the PHPDoc markup, and save to filesystem.
	 *
	 * @synopsis <directory> [<output_file>]
	 *
	 * @param array $args
	 */
	public function actions_snippets( $args ) {
		// $type = empty( $args[0] ) ? 'constants' : $args[0]; // constants, hooks
		$directory   = realpath( $args[0] );
		$output_file = empty( $args[1] ) ? 'all-hooks.sublime-completions' : $args[1];
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
				"comment"     => "Action Snippets's a-z",
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

									// wp_ajax_
									// admin_print_footer_scripts
									
									$args     = (array) $hook->getArgs();
									$params = array();

									foreach ($args as $k => $arg) {
										$h = $k;
										$arg = str_replace('$', '\$', $arg);
										if( ! empty( $arg ) && "''" !== $arg ) {
											$params[] = '${'.($h+4).':'. $arg .'}';
										}
									}

									// Snippet Code.
									$snippet_args = implode(', ', $params);


									$name = $hook->getName();
									$no_args = ( count($args) != 0 ) ? count($args) : ' ';
									WP_CLI::line( $type . ' | '.$no_args. ' | '  . $name );
									$name = str_replace('\'', '"', $name );
									// $name = str_replace('$', '\$', $name );
									$name = str_replace('{$', '${1:\$', $name );

									$snip_func_name = $name;
									$snip_func_name = sanitize_key( $snip_func_name );
									$snip_func_name = str_replace('$', '', $snip_func_name);
									$snip_func_name = str_replace('-', '_', $snip_func_name);
									$snip_func_name = str_replace('1', '', $snip_func_name);

									$contents  = "add_action( '" . $name . "', '\${2:$snip_func_name}_cb' ); ";
									$contents .= "\n\nfunction \${2:$snip_func_name}_cb( ".$snippet_args." ) {";
									$contents .= "\n\t\${10:// Code here...}";
									$contents .= "\n}";

									// Add hook.
									$output[ $snip_func_name ] = array(
										"trigger" =>  $hook->getName(),
										"contents" => $contents,
									);

									// 'name'      => $hook->getName(),
									// 'line'      => $hook->getLineNumber(),
									// 'end_line'  => $hook->getNode()->getAttribute( 'endLine' ),
									// 'type'      => $hook->getType(),
									// 'arguments' => $hook->getArgs(),
									// 'doc'       => export_docblock( $hook ),
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


		$without_key = array();
		foreach ($output as $key => $value) {
			$without_key[] = $value;
		}
		WP_CLI::line( "\n\nFound: " .count($output) . " actions!\n" );

		return $without_key;
		// return $output;
	}
}
