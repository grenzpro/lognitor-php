#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"
composer test
echo "✓ Tests passed. Push to GitHub for Packagist auto-update."
