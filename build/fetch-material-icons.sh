#!/bin/bash

set -x
set -e

REPO_URL="https://github.com/Templarian/MaterialDesign"
CLONE_PATH="/tmp/material-design-icons"
TARGET_PATH="$PWD/img/material"

if [ ! -d "${CLONE_PATH}" ]; then
    git clone "${REPO_URL}" --depth 1 "${CLONE_PATH}"
fi

mkdir -p "$TARGET_PATH"

cp $CLONE_PATH/LICENSE $TARGET_PATH/LICENSE

cat $CLONE_PATH/meta.json |
    jq '.[]|select(.author == "Google" and (.name | contains("-") | not))' > $TARGET_PATH/meta.json

cat $TARGET_PATH/meta.json |
    jq " .name | \"$CLONE_PATH/svg/\" + . + \".svg\"" -r  |
    xargs -I{} cp {} $PWD/img/material/

find "${TARGET_PATH}" -name '*.svg' -exec sed -i 's_/></svg>_fill=\"#fff\" /></svg>_' {} + ;
