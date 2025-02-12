<?php

/**
 * Recursive action to fetch data
 *
 * @param CurlHandle $curl
 * @param string $url
 * @param array $headers
 * @param callable $after_fetching
 * @return void
 */
function get_action( CurlHandle &$curl, string $url, array $headers, callable $after_fetching ) : void {
    curl_setopt_array( $curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => $headers,
    ) );
    echo "[+] Fetching data for : '$url'\n";
    $response = curl_exec( $curl );
    if( $response === false ) {
        $error = curl_error( $curl );
        $errno = curl_errno( $curl );
        echo "[!] Error fetching '$url' : ($errno) $error\n";
        return;
    }
    $decoded_response = json_decode( $response, true );
    $result = $after_fetching( $response, $decoded_response, $url );
    if( $result ) get_action( $curl, $url, $headers, $after_fetching );
    else {
        echo "[+] Stop fetching.\n";
    }
}

/**
 * Prepare everything for fetching
 *
 * @param array $replacement
 * @param array $headers
 * @param callable $after_fetching
 * @param string $url
 * @return void
 */
function get_data(
    array $replacement,
    array $headers,
    callable $after_fetching,
    string $url="https://discord.com/api/v9/channels/[CHAN]/messages?limit=[LIMIT]&author=[AUTHOR]"
) : void {
    if( preg_match_all( '/\[(\w+)\]/', $url, $matches ) ) {
        $matches[ 1 ] = array_map( fn( string $x ) => strtolower( $x ), $matches[ 1 ] );
        echo "[+] URL replacements needed for '$url' : " . join( ', ', $matches[ 1 ] ) . ".\n";
        $replacement_keys = array_map( fn( string $x ) => strtolower( $x ), array_keys( $replacement ) );
        $keys_found = array();
        foreach( $matches[ 1 ] as $loop_match ) {
            if( in_array(
                strtolower( $loop_match ),
                $replacement_keys
            ) ) {
                echo "[+] --> replacement found '$loop_match'.\n";
                $keys_found[] = strtolower( $loop_match );
            }
        }
        $missing = array_diff(
            $matches[ 1 ],
            $keys_found
        );
        if( count( $missing ) !== 0 ) {
            echo "[!] Missing replacements values for '$url' : " . join( ', ', $missing ) . ".\n";
            exit;
        }
        echo "[+] Preparing cURL.\n";
        $curl = curl_init();
        if( $curl === false ) {
            echo "[!] cURL init failed.\n";
            exit;
        }
        foreach( $keys_found as $loop_key ) {
            $url = str_replace( "[$loop_key]", array_values( $replacement )[ array_search( $loop_key, $replacement_keys ) ], strtolower( $url ) );
        }
        echo "[+] First URL : '$url'\n";
        get_action(
            $curl,
            $url,
            $headers,
            $after_fetching
        );
        curl_close( $curl );
        echo "[+] End fetching data.\n";
    } else {
        echo "[!] Missing templates in the url : '$url'.\n";
    }
}

$final = array();

get_data(
    array(
        'limit' => 100,
        'chan' => "CHAN_ID",
        'author' => "AUTHOR_ID"
    ),
    headers: array(
        'accept: */*',
        'accept-language: en-US,en;q=0.9',
        'authorization: [YOUR_AUTH_HERE]',
        'priority: u=1, i',
        'referer: https://discord.com/channels/@me',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "macOS"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin',
        'x-debug-options: bugReporterEnabled',
        'x-discord-locale: en-US'
    ),
    after_fetching: function( string $raw_result, array $decoded_result, string &$url ) use ( &$final ) : bool {
        if( empty( $decoded_result ) ) return false;
        $final[] = $decoded_result;
        $last = end( $decoded_result );
        echo "[+] New before ID : " . $last[ 'id' ] . "\n";
        if( preg_match( '/before=([0-9]*)/', $url, $matches ) ) $url = str_replace( $matches[ 1 ], $last[ 'id' ], $url );
        else $url = "$url&before=" . $last[ 'id' ];
        echo "[+] New URL : '$url'\n";
        usleep( 1200 );
        return true;
    }
);

file_put_contents( 'fetched.json', json_encode( $final, JSON_PRETTY_PRINT ) );
