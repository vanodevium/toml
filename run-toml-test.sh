#!/usr/bin/env bash

# requires TOML test binary
# go install github.com/toml-lang/toml-test/cmd/toml-test@latest

skip_decode=(
    -skip='valid/key/quoted-unicode'
	-skip='invalid/encoding/bad-utf8-*'
	-skip='invalid/encoding/bad-codepoint'
)

skip_encode=(
    -skip='valid/key/quoted-unicode'
	-skip='valid/float/max-int'
    -skip='valid/float/long'
	-skip='valid/spec/float-1'
)

e=0
toml-test          "${skip_decode[@]}" ./toml-test-decode.php || e=1
toml-test -encoder "${skip_encode[@]}" ./toml-test-encode.php || e=1
exit $e
