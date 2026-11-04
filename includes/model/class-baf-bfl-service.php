<?php
/**
 * BFL for the REST API and the builder's BFL and Structure modes.
 *
 * Every BFL operation the site offers, on the form model: reading with the
 * BFL 1 reader (which also reads every older spelling), writing with the
 * canonical writer, checking against the schema and the model's own rules,
 * and the builder's flat shape written from the model so the visual builder
 * and the renderer keep working. The older parser in includes/bfl/ is no
 * longer used for any of these; it stays for the older file importer and
 * formatter, which read BFL written before the model.
 *
 * No WordPress functions are used, so the tests run without WordPress.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_BFL_Service class.
 */
class BAF_BFL_Service {

    /**
     * Read BFL.
     *
     * @param string $bfl BFL text.
     * @return array Keys: valid, model, config (the builder's shape, written
     *               from the model; null when the text has errors), title,
     *               settings, errors, warnings (each array( line, message )).
     */
    public static function parse( $bfl ) {
        $read = BAF_BFL_Reader::read( (string) $bfl );
        $out  = array(
            'valid'    => ! $read['errors'],
            'model'    => $read['model'],
            'config'   => null,
            'errors'   => $read['errors'],
            'warnings' => $read['warnings'],
        );
        if ( ! $read['errors'] ) {
            $stored          = BAF_Form_Model_Converter::to_legacy( $read['model'] );
            $out['config']   = $stored['config'];
            $out['title']    = $stored['title'];
            $out['settings'] = $stored['settings'];
        }
        return $out;
    }

    /**
     * Check BFL: the reader's line-numbered problems, then the model's
     * schema and rules (JSON pointer paths).
     *
     * @param string $bfl BFL text.
     * @return array Keys: valid, errors, warnings, model_errors.
     */
    public static function validate( $bfl ) {
        $read   = BAF_BFL_Reader::read( (string) $bfl );
        $model  = $read['errors'] ? array() : BAF_Form_Model::validate( $read['model'] );
        return array(
            'valid'        => ! $read['errors'] && ! $model,
            'errors'       => $read['errors'],
            'warnings'     => $read['warnings'],
            'model_errors' => $model,
        );
    }

    /**
     * Write BFL from a model, or from the builder's stored shape.
     *
     * @param array $input A model (version and pages), or a stored form or
     *                     builder config (fields, pages).
     * @return array Keys: bfl, model, report (what did not convert, from a builder config).
     */
    public static function compile( $input ) {
        $input = is_array( $input ) ? $input : array();
        if ( self::is_model( $input ) ) {
            return array( 'bfl' => BAF_BFL_Writer::write( $input ), 'model' => $input, 'report' => null );
        }
        $stored = isset( $input['config'] ) ? $input : array(
            'title'  => $input['title'] ?? '',
            'config' => $input,
        );
        $result = BAF_Form_Model_Converter::from_legacy( $stored );
        return array(
            'bfl'    => BAF_BFL_Writer::write( $result['model'] ),
            'model'  => $result['model'],
            'report' => $result['report']->to_array(),
        );
    }

    /**
     * Rewrite BFL in canonical form.
     *
     * @param string $bfl BFL text.
     * @return array Keys: bfl (null when the text has errors), errors, warnings.
     */
    public static function format( $bfl ) {
        $read = BAF_BFL_Reader::read( (string) $bfl );
        return array(
            'bfl'      => $read['errors'] ? null : BAF_BFL_Writer::write( $read['model'] ),
            'errors'   => $read['errors'],
            'warnings' => $read['warnings'],
        );
    }

    /**
     * Whether an array is a form model rather than the builder's shape.
     *
     * @param array $v Value.
     * @return bool Model.
     */
    public static function is_model( $v ) {
        if ( ! isset( $v['version'], $v['pages'] ) || ! is_array( $v['pages'] ) || isset( $v['fields'] ) ) {
            return false;
        }
        foreach ( $v['pages'] as $page ) {
            if ( ! is_array( $page ) || ! array_key_exists( 'items', $page ) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * The form as a tree for Structure mode: pages, then items with their
     * children, each with the sentence of its condition.
     *
     * @param array $model Model.
     * @return array List of pages: array( id, title, when (sentence or ''), items ).
     *               Each item: array( id, type, label, required, when, children ).
     */
    public static function outline( $model ) {
        $labels  = BAF_Form_Model::labels( $model );
        $choices = BAF_Form_Model::choice_labels( $model );
        $say     = function ( $text ) use ( $labels, $choices ) {
            if ( ! is_string( $text ) || $text === '' ) {
                return '';
            }
            try {
                return BAF_Expression::to_sentence( BAF_Expression::parse( $text ), $labels, $choices );
            } catch ( BAF_Expression_Error $e ) {
                return $text;
            }
        };
        $walk = function ( $items ) use ( &$walk, $say ) {
            $out = array();
            foreach ( $items as $item ) {
                $children = array();
                if ( ( $item['type'] ?? '' ) === 'columns' ) {
                    foreach ( $item['columns'] ?? array() as $col ) {
                        $children = array_merge( $children, $walk( $col['items'] ?? array() ) );
                    }
                } elseif ( isset( $item['items'] ) && is_array( $item['items'] ) ) {
                    $children = $walk( $item['items'] );
                }
                $out[] = array(
                    'id'       => (string) ( $item['id'] ?? '' ),
                    'type'     => (string) ( $item['type'] ?? 'text' ),
                    'label'    => (string) ( $item['label'] ?? '' ),
                    'required' => ! empty( $item['required'] ),
                    'when'     => $say( $item['when'] ?? '' ),
                    'children' => $children,
                );
            }
            return $out;
        };
        $pages = array();
        foreach ( $model['pages'] ?? array() as $page ) {
            $pages[] = array(
                'id'    => (string) $page['id'],
                'title' => (string) ( $page['title'] ?? '' ),
                'when'  => $say( $page['when'] ?? '' ),
                'items' => $walk( $page['items'] ?? array() ),
            );
        }
        return $pages;
    }
}
