<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Prüft auf Plugin-Updates via GitHub Releases API.
 * Kein Fremd-Code – nur WordPress-eigene Funktionen (wp_remote_get, transients).
 */
class Easy_Event_Updater {

    private $plugin_file;   // Pfad zur Haupt-Plugin-Datei (relativ zu plugins/)
    private $plugin_slug;   // z.B. easy-event/easy-event.php
    private $github_user;   // GitHub-Benutzername
    private $github_repo;   // GitHub-Repository-Name
    private $current_version;

    // GitHub API-Antwort wird 12 Stunden gecacht
    const CACHE_TTL = 43200;

    public function __construct( $plugin_file, $github_user, $github_repo ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_slug     = plugin_basename( $plugin_file );
        $this->github_user     = $github_user;
        $this->github_repo     = $github_repo;
        $this->current_version = get_plugin_data( $plugin_file )['Version'];

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api',                           array( $this, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_post_install',                 array( $this, 'after_install' ), 10, 3 );
    }

    // ------------------------------------------------------------------
    // GitHub API abfragen (mit Cache)
    // ------------------------------------------------------------------

    private function get_release_info() {
        $cache_key = 'easy_event_github_release';
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $url      = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
            ),
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            // Bei Fehler kurz cachen (5 Min.) damit nicht bei jedem Request GitHub angefragt wird
            set_transient( $cache_key, null, 300 );
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        set_transient( $cache_key, $release, self::CACHE_TTL );

        return $release;
    }

    // Hilfsfunktion: Download-URL aus den Release-Assets holen
    private function get_download_url( $release ) {
        // Zuerst: angehängtes ZIP-Asset suchen
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( isset( $asset->browser_download_url ) &&
                     str_ends_with( strtolower( $asset->browser_download_url ), '.zip' ) ) {
                    return $asset->browser_download_url;
                }
            }
        }
        // Fallback: automatisch generiertes ZIP von GitHub
        return "https://github.com/{$this->github_user}/{$this->github_repo}/archive/refs/tags/{$release->tag_name}.zip";
    }

    // ------------------------------------------------------------------
    // Hook: WordPress Update-Check
    // ------------------------------------------------------------------

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_release_info();
        if ( ! $release || empty( $release->tag_name ) ) {
            return $transient;
        }

        // Tag «v1.2.0» → «1.2.0»
        $remote_version = ltrim( $release->tag_name, 'v' );

        if ( version_compare( $this->current_version, $remote_version, '<' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) array(
                'slug'        => dirname( $this->plugin_slug ),
                'plugin'      => $this->plugin_slug,
                'new_version' => $remote_version,
                'url'         => "https://github.com/{$this->github_user}/{$this->github_repo}",
                'package'     => $this->get_download_url( $release ),
            );
        }

        return $transient;
    }

    // ------------------------------------------------------------------
    // Hook: Plugin-Info-Popup («Details anzeigen»)
    // ------------------------------------------------------------------

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }
        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $release = $this->get_release_info();
        if ( ! $release ) {
            return $result;
        }

        $remote_version = ltrim( $release->tag_name, 'v' );

        return (object) array(
            'name'          => 'Easy Event',
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => $remote_version,
            'author'        => $this->github_user,
            'homepage'      => "https://github.com/{$this->github_user}/{$this->github_repo}",
            'download_link' => $this->get_download_url( $release ),
            'sections'      => array(
                'description' => ! empty( $release->body ) ? nl2br( esc_html( $release->body ) ) : 'Plugin für Event-Anmeldungen.',
                'changelog'   => ! empty( $release->body ) ? nl2br( esc_html( $release->body ) ) : '',
            ),
            'last_updated'  => $release->published_at ?? '',
            'requires'      => '5.8',
            'tested'        => '7.0',
            'requires_php'  => '7.4',
        );
    }

    // ------------------------------------------------------------------
    // Hook: Nach der Installation – Ordnername korrigieren
    // ------------------------------------------------------------------
    // GitHub benennt den entpackten Ordner «easy-event-1.2.0» statt «easy-event».
    // Dieser Hook korrigiert das automatisch.

    public function after_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $response;
        }

        global $wp_filesystem;
        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_slug );
        $wp_filesystem->move( $result['destination'], $plugin_folder, true );
        $result['destination'] = $plugin_folder;

        return $result;
    }
}
