#!/usr/bin/env bash

# requires TOML test binary
# go install github.com/toml-lang/toml-test/v2/cmd/toml-test@latest

skip_decode=(
    -skip='valid/key/quoted-unicode'
)

skip_encode=(
	-skip='encoder/key/quoted-unicode'
)

e=0
toml-test test -toml=1.0 "${skip_decode[@]}" "${skip_encode[@]}" -decoder='php ./toml-test-decode.php' -encoder='php ./toml-test-encode.php' || e=1
exit $e
