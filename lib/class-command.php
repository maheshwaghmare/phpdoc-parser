<?php

namespace WP_Parser;

use WP_CLI;
use WP_CLI_Command;

/**
 * Converts PHPDoc markup into a template ready for import to a WordPress blog.
 */
class Command extends WP_CLI_Command {

	private static $limit = 0;

	/**
	 * Generate a JSON file containing the PHPDoc markup, and save to filesystem.
	 *
	 * @synopsis <directory> [<output_file>]
	 *
	 * @param array $args
	 */
	public function export( $args ) {
		$directory   = realpath( $args[0] );
		$output_file = empty( $args[1] ) ? 'phpdoc.json' : $args[1];
		$json        = $this->_get_phpdoc_data( $directory );
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
	 * Generate a JSON file containing the PHPDoc markup, and save to filesystem.
	 *
	 * @synopsis <directory> [<output_file>]
	 *
	 * E.g.
	 * wp parser define_constants .
	 * 
	 * @param array $args
	 */
	public function define_constants( $args ) {
		$type = 'define-constants';
		$directory   = realpath( $args[0] );
		$output_file = empty( $args[1] ) ? $type . '.json' : $args[1];

		// WP_CLI::line( $type );
		WP_CLI::line( $directory );
		WP_CLI::line( $output_file );
		// WP_CLI::error( 'ok' );
		
		// $json        = $this->_get_phpdoc_data( $directory );
		$json        = $this->_sublime_get_phpdoc_data( $type, $directory );
		$result      = file_put_contents( 'sublime/' . $output_file, $json );
		WP_CLI::line();

		if ( false === $result ) {
			WP_CLI::error( sprintf( 'Problem writing %1$s bytes of data to %2$s', strlen( $json ), $output_file ) );
			exit;
		}

		WP_CLI::success( sprintf( 'Data exported to %1$s', $output_file ) );
		WP_CLI::line();
	}

	/**
	 * Generate a JSON file containing the PHPDoc markup, and save to filesystem.
	 *
	 * @synopsis <directory> [<output_file>]
	 *
	 * E.g.
	 * wp parser use_constants .
	 * 
	 * @param array $args
	 */
	public function use_constants( $args ) {
		$type = 'use-constants';
		$directory   = realpath( $args[0] );
		$output_file = empty( $args[1] ) ? $type . '.json' : $args[1];

		// WP_CLI::line( $type );
		WP_CLI::line( $directory );
		WP_CLI::line( $output_file );
		// WP_CLI::error( 'ok' );
		
		// $json        = $this->_get_phpdoc_data( $directory );
		$json        = $this->_sublime_get_phpdoc_data( $type, $directory );
		$result      = file_put_contents( 'sublime/' . $output_file, $json );
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
		// array_unique( $output );

		if ( 'json' == $format ) {

			$data = array(
				"scope"       => "source.php - variable.other.php",
				"comment"     => ucwords( str_replace('-', ' ', $type)) . " (".count( $output ).")",
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

		$filecount = 1;
		foreach ( $files as $filename ) {

			// if( self::$limit <= 2 ) {

				$file = new File_Reflector( $filename );

				$path = ltrim( substr( $filename, strlen( $root ) ), DIRECTORY_SEPARATOR );

				// Avoid 'wp-content' Directory
				if( strpos($file->getFilename(), 'wp-content') === false ) {
					WP_CLI::line( $type . ' ' . $filecount . ' ' . $path );
					$filecount++;

					$file->setFilename( $path );

					$file->process();

					// // // TODO proper exporter
					// // $out = array(
					// // 	'file' => export_docblock( $file ),
					// // 	'path' => str_replace( DIRECTORY_SEPARATOR, '/', $file->getFilename() ),
					// // 	'root' => $root,
					// // );

					// // if ( ! empty( $file->uses ) ) {
					// // 	$out['uses'] = export_uses( $file->uses );
					// // }

					// // foreach ( $file->getIncludes() as $include ) {
					// // 	$out['includes'][] = array(
					// // 		'name' => $include->getName(),
					// // 		'line' => $include->getLineNumber(),
					// // 		'type' => $include->getType(),
					// // 	);
					// // }
					
					if( 'define-constants' === $type || 'use-constants' === $type ) {
						foreach ( $file->getConstants() as $constant ) {
							if( ! in_array($constant->getShortName(), $output) ) {
								$output[] = $constant->getShortName();
								WP_CLI::line( $constant->getShortName() );

								if( 'define-constants' === $type ) {
									$contents = "define( '".$constant->getShortName()."', \${1:".$constant->getValue()."} );";
								// } else if( 'use-constants' === $type ) {
								} else {
									$contents = $constant->getShortName();
								}

								$output[] = array(
									"trigger"  => $constant->getShortName() . "\tWP " . ucwords(str_replace('-', ' ', $type)),
									"contents" => $contents,
									// "contents" => "define( '".$constant->getShortName()."', ${1:".$constant->getValue()."} );",
								);
								// $out['constants'][] = array(
								// 	'name'  => $constant->getShortName(),
								// 	'line'  => $constant->getLineNumber(),
								// 	'value' => $constant->getValue(),
								// );
							}
						}
					}

					// // 
					// // Hooks
					// // $ wp parser export wp-admin "C:\Users\intel\AppData\Roaming\Sublime Text 3\Packages\WordPress Code Reference\all-hooks-wp-admin-.json"
					// // $ wp parser export wp-includes "C:\Users\intel\AppData\Roaming\Sublime Text 3\Packages\WordPress Code Reference\all-hooks-wp-includes-.json"

					// // $ wp parser sublime wp-admin "C:\Users\intel\AppData\Roaming\Sublime Text 3\Packages\WordPress Code Reference\all-hooks-wp-admin-.sublime-completions"
					// // $ wp parser sublime wp-includes "C:\Users\intel\AppData\Roaming\Sublime Text 3\Packages\WordPress Code Reference\all-hooks-wp-includes-.sublime-completions"
					// // if( 'hooks' === $type ) {
					// 	if ( ! empty( $file->uses['hooks'] ) ) {
					// 		// $output[] = export_hooks( $file->uses['hooks'] );
					// 		$hooks = (array) $file->uses['hooks'];

					// 		foreach ( $hooks as $hook ) {
					// 			WP_CLI::line( $hook->getName() );

					// 			$args     = (array) $hook->getArgs();
					// 			$contents = '';
					// 			foreach ($args as $k => $arg) {
					// 				$arg = str_replace('$', '\$', $arg);
					// 				$contents .= '${'.($k+1).':'. $arg .'} ';
					// 			}

					// 			$type = $hook->getType();

					// 			if( 'action' === $type ) {
					// 				$h = 'add_action( "';
					// 			} else {
					// 				$h = 'add_filter( "';
					// 			}

					// 			$name = str_replace('$', '\$', $hook->getName() );

					// 			$output[] = array(
					// 				"trigger" =>  $name . '\\t' . $hook->getType(),
					// 				"contents" => $h . $name . '"' . trim( $contents ) . '" );',
					// 			);

					// 			// 'name'      => $hook->getName(),
					// 			// 'line'      => $hook->getLineNumber(),
					// 			// 'end_line'  => $hook->getNode()->getAttribute( 'endLine' ),
					// 			// 'type'      => $hook->getType(),
					// 			// 'arguments' => $hook->getArgs(),
					// 			// 'doc'       => export_docblock( $hook ),
					// 		}

					// 		// $out['hooks'] = export_hooks( $file->uses['hooks'] );
					// 	}
					// // }

					// // foreach ( $file->getFunctions() as $function ) {
					// // 	$func = array(
					// // 		'name'      => $function->getShortName(),
					// // 		'namespace' => $function->getNamespace(),
					// // 		'aliases'   => $function->getNamespaceAliases(),
					// // 		'line'      => $function->getLineNumber(),
					// // 		'end_line'  => $function->getNode()->getAttribute( 'endLine' ),
					// // 		'arguments' => export_arguments( $function->getArguments() ),
					// // 		'doc'       => export_docblock( $function ),
					// // 		'hooks'     => array(),
					// // 	);

					// // 	if ( ! empty( $function->uses ) ) {
					// // 		$func['uses'] = export_uses( $function->uses );

					// // 		if ( ! empty( $function->uses['hooks'] ) ) {
					// // 			$func['hooks'] = export_hooks( $function->uses['hooks'] );
					// // 		}
					// // 	}

					// // 	$out['functions'][] = $func;
					// // }

					// // foreach ( $file->getClasses() as $class ) {
					// // 	$class_data = array(
					// // 		'name'       => $class->getShortName(),
					// // 		'namespace'  => $class->getNamespace(),
					// // 		'line'       => $class->getLineNumber(),
					// // 		'end_line'   => $class->getNode()->getAttribute( 'endLine' ),
					// // 		'final'      => $class->isFinal(),
					// // 		'abstract'   => $class->isAbstract(),
					// // 		'extends'    => $class->getParentClass(),
					// // 		'implements' => $class->getInterfaces(),
					// // 		'properties' => export_properties( $class->getProperties() ),
					// // 		'methods'    => export_methods( $class->getMethods() ),
					// // 		'doc'        => export_docblock( $class ),
					// // 	);

					// // 	$out['classes'][] = $class_data;
					// // }

					// // $output[] = $out;
					
					// // self::$limit++;
				}

				
			// }

		}

		return $output;
	}

	/**
	 * Read a JSON file containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
	 *
	 * @synopsis <file> [--quick] [--import-internal]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function import( $args, $assoc_args ) {
		list( $file ) = $args;
		WP_CLI::line();

		// Get the data from the <file>, and check it's valid.
		$phpdoc = false;

		if ( is_readable( $file ) ) {
			$phpdoc = file_get_contents( $file );
		}

		if ( ! $phpdoc ) {
			WP_CLI::error( sprintf( "Can't read %1\$s. Does the file exist?", $file ) );
			exit;
		}

		$phpdoc = json_decode( $phpdoc, true );
		if ( is_null( $phpdoc ) ) {
			WP_CLI::error( sprintf( "JSON in %1\$s can't be decoded :(", $file ) );
			exit;
		}

		// Import data
		$this->_do_import( $phpdoc, isset( $assoc_args['quick'] ), isset( $assoc_args['import-internal'] ) );
	}

	/**
	 * Generate JSON containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
	 *
	 * @subcommand create
	 * @synopsis   <directory> [--quick] [--import-internal] [--user]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function create( $args, $assoc_args ) {
		list( $directory ) = $args;
		$directory = realpath( $directory );

		if ( empty( $directory ) ) {
			WP_CLI::error( sprintf( "Can't read %1\$s. Does the file exist?", $directory ) );
			exit;
		}

		WP_CLI::line();

		// Import data
		$this->_do_import( $this->_get_phpdoc_data( $directory, 'array' ), isset( $assoc_args['quick'] ), isset( $assoc_args['import-internal'] ) );
	}

	/**
	 * Generate the data from the PHPDoc markup.
	 *
	 * @param string $path   Directory or file to scan for PHPDoc
	 * @param string $format What format the data is returned in: [json|array].
	 *
	 * @return string|array
	 */
	protected function _get_phpdoc_data( $path, $format = 'json' ) {
		WP_CLI::line( sprintf( 'Extracting PHPDoc from %1$s. This may take a few minutes...', $path ) );
		$is_file = is_file( $path );
		$files   = $is_file ? array( $path ) : get_wp_files( $path );
		$path    = $is_file ? dirname( $path ) : $path;

		if ( $files instanceof \WP_Error ) {
			WP_CLI::error( sprintf( 'Problem with %1$s: %2$s', $path, $files->get_error_message() ) );
			exit;
		}

		$output = parse_files( $files, $path );

		if ( 'json' == $format ) {
			return json_encode( $output, JSON_PRETTY_PRINT );
		}

		return $output;
	}

	/**
	 * Import the PHPDoc $data into WordPress posts and taxonomies
	 *
	 * @param array $data
	 * @param bool  $skip_sleep     If true, the sleep() calls are skipped.
	 * @param bool  $import_ignored If true, functions marked `@ignore` will be imported.
	 */
	protected function _do_import( array $data, $skip_sleep = false, $import_ignored = false ) {

		if ( ! wp_get_current_user()->exists() ) {
			WP_CLI::error( 'Please specify a valid user: --user=<id|login>' );
			exit;
		}

		// Run the importer
		$importer = new Importer;
		$importer->setLogger( new WP_CLI_Logger() );
		$importer->import( $data, $skip_sleep, $import_ignored );

		WP_CLI::line();
	}
}
