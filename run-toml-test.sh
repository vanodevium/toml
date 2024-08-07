#!/usr/bin/env bash

# requires TOML test binary
# go install github.com/toml-lang/toml-test/cmd/toml-test@latest

skip_decode=(
	-skip='invalid/encoding/bad-utf8-*'
	-skip='invalid/local-date/feb-29'
	-skip='invalid/local-date/feb-30'
	-skip='invalid/local-datetime/feb-29'
	-skip='invalid/local-datetime/feb-30'
	-skip='invalid/datetime/feb-30'
	-skip='invalid/datetime/feb-29'
	-skip='invalid/datetime/offset-overflow-hour'

	-skip='valid/key/quoted-unicode'
	-skip='invalid/encoding/bad-codepoint'
)

skip_encode=(
	-skip='valid/inline-table/spaces'
	-skip='valid/float/zero'
	-skip='valid/float/exponent'
	-skip='valid/float/max-int'
  -skip='valid/float/long'
	-skip='valid/key/quoted-unicode'
	-skip='valid/comment/tricky'
	-skip='valid/spec/float-0'
	-skip='valid/spec/float-1'
	-skip='valid/integer/long'
)

e=0
toml-test          "${skip_decode[@]}" ./toml-test-decode.php || e=1
toml-test -encoder "${skip_encode[@]}" ./toml-test-encode.php || e=1
exit $e
