#!/bin/bash

# The texts of the code that runs (src: build/ is a copy of it)
find src -iname "*.php" | sort | xargs xgettext --from-code=UTF-8 --add-comments -o locales/messages.pot

pot=locales/messages.pot

for lang in fr_FR de_DE cy_GB it_IT nl_NL pt_BR
do
  file="locales/$lang/LC_MESSAGES/messages.po"

  mkdir -p "$(dirname "$file")" && touch $file \
  && gpt-po sync --po "$file" --pot "$pot" \
  && gpt-po translate --po "$file" -l $lang \
  && poedit "$file"

done
