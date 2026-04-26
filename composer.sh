#!/usr/bin/env bash
ARGS="$@"

if [ $# -eq 0 ]; then
	COMMAND="install"
	else
	COMMAND=$ARGS
	fi

docker run --rm --interactive --tty \
  --volume $PWD:/app \
   -u $(id -u ${USER}):$(id -g ${USER}) \
  composer:2 $COMMAND
