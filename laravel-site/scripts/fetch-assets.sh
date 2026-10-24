#!/usr/bin/env bash
set -euo pipefail

asset_directory="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../public/assets" && pwd)"
mkdir -p "$asset_directory"

ids=(
  1438761681033-6461ffad8d80
  1472099645785-5658abf4ff4e
  1507003211169-0a1dd7228f2d
  1508804185872-d7badad00f7d
  1559599746-8823b38544c6
  1562672753-8cccbe9806ce
  1573496359142-b8d87734a5a2
  1575110445943-15b543686b56
  1579592672790-39239b6cbc31
  1585793753011-397e6e4668d6
  1599700548547-10a85af5b390
  1617170788899-ef9587d6e63f
  1688269910608-e3c65eb4ac3a
)

for id in "${ids[@]}"; do
  destination="$asset_directory/$id.jpg"
  if [[ ! -f "$destination" ]]; then
    curl --fail --location --retry 3 \
      "https://images.unsplash.com/photo-${id}?w=1800&q=75&auto=format&fit=crop" \
      --output "$destination"
  fi
done
