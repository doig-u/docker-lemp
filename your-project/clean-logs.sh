#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/var/log
rm $DIR/nginx/* $DIR/php/* $DIR/mysql/* $DIR/supervisor/*
