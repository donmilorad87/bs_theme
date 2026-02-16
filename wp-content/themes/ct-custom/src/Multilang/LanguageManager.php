<?php
/**
 * Language Manager.
 *
 * Manages the languages.json registry file with file-locking
 * for concurrent write safety.
 *
 * @package BS_Custom
 */

namespace BSCustom\Multilang;

class LanguageManager {

    const MAX_LANGUAGES      = 50;
    const MAX_LOCALES_PER    = 20;
    const ALLOWED_FIELDS     = array( 'iso2', 'iso3', 'native_name', 'flag', 'locales', 'enabled', 'is_default' );
    const REQUIRED_ADD_FIELDS = array( 'iso2', 'native_name' );

    /** @var string Absolute path to the languages JSON file. */
    private $file_path;

    /** @var array|null Instance-level cache for read_file() result. */
    private $cached_data = null;

    /** @var array Cross-instance cache keyed by file path. */
    private static $static_cache = array();

    /**
     * @param string $file_path Optional path override (for testing).
     */
    public function __construct( string $file_path = '' ) {
        assert( is_string( $file_path ), 'file_path must be a string' );

        if ( '' === $file_path ) {
            $file_path = $this->default_file_path();
        }

        $this->file_path = $file_path;
    }

    /**
     * Get all languages.
     *
     * @return array<int, array>
     */
    public function get_all(): array {
        $data = $this->read_file();

        assert( is_array( $data ), 'Languages data must be an array' );

        return $data;
    }

    /**
     * Get only enabled languages.
     *
     * @return array<int, array>
     */
    public function get_enabled(): array {
        $all     = $this->get_all();
        $enabled = array();
        $max     = self::MAX_LANGUAGES;
        $count   = 0;

        foreach ( $all as $lang ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            if ( ! empty( $lang['enabled'] ) ) {
                $enabled[] = $lang;
            }
        }

        return $enabled;
    }

    /**
     * Get the default language.
     *
     * @return array|null
     */
    public function get_default(): ?array {
        $all   = $this->get_all();
        $max   = self::MAX_LANGUAGES;
        $count = 0;

        foreach ( $all as $lang ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            if ( ! empty( $lang['is_default'] ) ) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * Get a language by its iso2 code.
     *
     * @param string $iso2 Two-letter code.
     * @return array|null
     */
    public function get_by_iso2( string $iso2 ): ?array {
        assert( is_string( $iso2 ), 'iso2 must be a string' );

        $all   = $this->get_all();
        $max   = self::MAX_LANGUAGES;
        $count = 0;

        foreach ( $all as $lang ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            if ( isset( $lang['iso2'] ) && $lang['iso2'] === $iso2 ) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * Add a new language.
     *
     * @param array $data Language data (must include iso2, native_name).
     * @return bool
     */
    public function add( array $data ): bool {
        assert( is_array( $data ), 'Language data must be an array' );

        /* Validate required fields */
        $max_fields = 10;
        $field_count = 0;
        foreach ( self::REQUIRED_ADD_FIELDS as $field ) {
            if ( $field_count >= $max_fields ) {
                break;
            }
            $field_count++;

            if ( empty( $data[ $field ] ) ) {
                return false;
            }
        }

        $all = $this->get_all();

        /* Check limit */
        if ( count( $all ) >= self::MAX_LANGUAGES ) {
            return false;
        }

        /* Check duplicate iso2 */
        $iso2  = $data['iso2'];
        $max   = self::MAX_LANGUAGES;
        $count = 0;

        foreach ( $all as $lang ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            if ( isset( $lang['iso2'] ) && $lang['iso2'] === $iso2 ) {
                return false;
            }
        }

        $language = array(
            'id'          => 'lang_' . $iso2 . '_' . substr( md5( uniqid( '', true ) ), 0, 8 ),
            'iso2'        => $iso2,
            'iso3'        => isset( $data['iso3'] ) ? $data['iso3'] : '',
            'native_name' => $data['native_name'],
            'flag'        => isset( $data['flag'] ) ? $data['flag'] : '',
            'locales'     => isset( $data['locales'] ) && is_array( $data['locales'] ) ? $data['locales'] : array(),
            'enabled'     => true,
            'is_default'  => false,
        );

        $all[] = $language;

        return $this->write_file( $all );
    }

    /**
     * Update an existing language.
     *
     * @param string $iso2 Language to update.
     * @param array  $data Fields to update.
     * @return bool
     */
    public function update( string $iso2, array $data ): bool {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );
        assert( is_array( $data ), 'data must be an array' );

        $all   = $this->get_all();
        $found = false;
        $max   = self::MAX_LANGUAGES;
        $count = 0;

        foreach ( $all as $idx => $lang ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            if ( isset( $lang['iso2'] ) && $lang['iso2'] === $iso2 ) {
                $allowed_max   = 10;
                $allowed_count = 0;
                foreach ( self::ALLOWED_FIELDS as $field ) {
                    if ( $allowed_count >= $allowed_max ) {
                        break;
                    }
                    $allowed_count++;

                    if ( array_key_exists( $field, $data ) ) {
                        $all[ $idx ][ $field ] = $data[ $field ];
                    }
                }
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return false;
        }

        return $this->write_file( $all );
    }

    /**
     * Remove a language by iso2. Cannot remove the default language.
     *
     * @param string $iso2 Language to remove.
     * @return bool
     */
    public function remove( string $iso2 ): bool {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );

        $all     = $this->get_all();
        $new     = array();
        $found   = false;
        $max     = self::MAX_LANGUAGES;
        $count   = 0;

        foreach ( $all as $lang ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            if ( isset( $lang['iso2'] ) && $lang['iso2'] === $iso2 ) {
                /* Cannot remove default */
                if ( ! empty( $lang['is_default'] ) ) {
                    return false;
                }
                $found = true;
                continue;
            }

            $new[] = $lang;
        }

        if ( ! $found ) {
            return false;
        }

        return $this->write_file( $new );
    }

    /**
     * Set a language as the default.
     *
     * @param string $iso2 Language to make default.
     * @return bool
     */
    public function set_default( string $iso2 ): bool {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );

        $all   = $this->get_all();
        $found = false;
        $max   = self::MAX_LANGUAGES;
        $count = 0;

        foreach ( $all as $idx => $lang ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            $all[ $idx ]['is_default'] = ( isset( $lang['iso2'] ) && $lang['iso2'] === $iso2 );

            if ( $all[ $idx ]['is_default'] ) {
                $found = true;
            }
        }

        if ( ! $found ) {
            return false;
        }

        return $this->write_file( $all );
    }

    /**
     * Set enabled/disabled for a language.
     *
     * @param string $iso2    Language code.
     * @param bool   $enabled Whether to enable.
     * @return bool
     */
    public function set_enabled( string $iso2, bool $enabled ): bool {
        return $this->update( $iso2, array( 'enabled' => $enabled ) );
    }

    /**
     * Add a locale to a language.
     *
     * @param string $iso2   Language code.
     * @param string $locale Locale string (e.g. en_AU).
     * @return bool
     */
    public function add_locale( string $iso2, string $locale ): bool {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );
        assert( ! empty( $locale ), 'locale must not be empty' );

        $lang = $this->get_by_iso2( $iso2 );

        if ( null === $lang ) {
            return false;
        }

        $locales = isset( $lang['locales'] ) && is_array( $lang['locales'] ) ? $lang['locales'] : array();

        if ( count( $locales ) >= self::MAX_LOCALES_PER ) {
            return false;
        }

        if ( in_array( $locale, $locales, true ) ) {
            return false;
        }

        $locales[] = $locale;

        return $this->update( $iso2, array( 'locales' => $locales ) );
    }

    /**
     * Remove a locale from a language.
     *
     * @param string $iso2   Language code.
     * @param string $locale Locale string.
     * @return bool
     */
    public function remove_locale( string $iso2, string $locale ): bool {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );
        assert( ! empty( $locale ), 'locale must not be empty' );

        $lang = $this->get_by_iso2( $iso2 );

        if ( null === $lang ) {
            return false;
        }

        $locales = isset( $lang['locales'] ) && is_array( $lang['locales'] ) ? $lang['locales'] : array();
        $new     = array();
        $found   = false;
        $max     = self::MAX_LOCALES_PER;
        $count   = 0;

        foreach ( $locales as $loc ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            if ( $loc === $locale ) {
                $found = true;
                continue;
            }
            $new[] = $loc;
        }

        if ( ! $found ) {
            return false;
        }

        return $this->update( $iso2, array( 'locales' => $new ) );
    }

    /**
     * Get the file path.
     *
     * @return string
     */
    public function get_file_path(): string {
        return $this->file_path;
    }

    /**
     * Default file path to the languages.json in theme translations dir.
     *
     * @return string
     */
    private function default_file_path(): string {
        if ( function_exists( 'get_template_directory' ) ) {
            return get_template_directory() . '/translations/languages.json';
        }

        return dirname( __DIR__, 2 ) . '/translations/languages.json';
    }

    /**
     * Read and decode the JSON file.
     *
     * @return array
     */
    private function read_file(): array {
        if ( null !== $this->cached_data ) {
            return $this->cached_data;
        }

        if ( isset( self::$static_cache[ $this->file_path ] ) ) {
            $this->cached_data = self::$static_cache[ $this->file_path ];
            return $this->cached_data;
        }

        if ( ! file_exists( $this->file_path ) || ! is_readable( $this->file_path ) ) {
            return array();
        }

        $content = file_get_contents( $this->file_path );

        if ( false === $content || '' === $content ) {
            return array();
        }

        $data = json_decode( $content, true );

        if ( ! is_array( $data ) ) {
            return array();
        }

        $this->cached_data = $data;
        self::$static_cache[ $this->file_path ] = $data;

        return $data;
    }

    /**
     * Write the language array to the JSON file with file locking.
     *
     * @param array $data Languages array.
     * @return bool
     */
    private function write_file( array $data ): bool {
        assert( is_array( $data ), 'Data must be an array' );

        $dir = dirname( $this->file_path );

        if ( ! is_dir( $dir ) ) {
            $created = mkdir( $dir, 0755, true );
            if ( ! $created ) {
                return false;
            }
        }

        $json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

        if ( false === $json ) {
            return false;
        }

        $handle = fopen( $this->file_path, 'c' );

        if ( false === $handle ) {
            return false;
        }

        if ( ! flock( $handle, LOCK_EX ) ) {
            fclose( $handle );
            return false;
        }

        ftruncate( $handle, 0 );
        rewind( $handle );
        fwrite( $handle, $json );
        fflush( $handle );
        flock( $handle, LOCK_UN );
        fclose( $handle );

        $this->cached_data = $data;
        self::$static_cache[ $this->file_path ] = $data;

        return true;
    }
}
