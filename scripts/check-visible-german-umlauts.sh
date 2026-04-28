#!/usr/bin/env bash
set -euo pipefail

MODE="${1:---source}"

SOURCE_PATTERN='(^|[^[:alpha:]])(fuer|Fuer|koennen|Koennen|Fuehrung|fuehrung|naechste|Naechste|naechstes|Naechstes|muessen|Muessen|frueh|Frueh|spaeter|Spaeter|zusammenhaeng|Zusammenhaeng|Produktionsuebergabe|qualitaet|Qualitaet|verlaesslich|Verlaesslich|schoen|Schoen|gruen|Gruen|weiss|Weiss|waehlen|Waehlen|wuenschen|Wuenschen|Spass|spass)([^[:alpha:]]|$)'
DB_PATTERN='(^|[^[:alpha:]])(fuer|Fuer|ueber|Ueber|koennen|Koennen|Fuehrung|fuehrung|naechste|Naechste|naechstes|Naechstes|muessen|Muessen|frueh|Frueh|spaeter|Spaeter|zusammenhaeng|Zusammenhaeng|Produktionsuebergabe|qualitaet|Qualitaet|verlaesslich|Verlaesslich|schoen|Schoen|gruen|Gruen|weiss|Weiss|waehlen|Waehlen|wuenschen|Wuenschen|Spass|spass)([^[:alpha:]]|$)'

usage() {
  cat <<'EOF'
Usage:
  scripts/check-visible-german-umlauts.sh --source
  scripts/check-visible-german-umlauts.sh --db
  scripts/check-visible-german-umlauts.sh --all

Checks visible German demo/project text for ASCII transliterations such as
"fuer", "koennen" or "Fuehrung". URL slugs like /ueber-uns are intentionally
not blocked by the source check.
EOF
}

check_source() {
  local matches grep_status

  set +e
  matches="$(
    git --no-pager grep -nE "${SOURCE_PATTERN}" -- \
      README.md \
      docs \
      packages/site-package \
      var \
      ':!var/log' \
      ':!var/cache' \
      ':!var/session' \
      ':!var/transient' \
      ':!*.lock'
  )"
  grep_status=$?
  set -e

  if [ "${grep_status}" -gt 1 ]; then
    echo "Source-Umlaut-Check konnte nicht ausgeführt werden."
    exit "${grep_status}"
  fi

  if [ -n "${matches}" ]; then
    echo "ASCII-Umlaut-Schreibweisen in Projekt-/Demo-Dateien gefunden:"
    echo "${matches}"
    echo
    echo "Bitte sichtbaren deutschen Text mit Umlauten schreiben."
    exit 1
  fi

  echo "✓ Source-Umlaut-Check OK"
}

check_db() {
  if ! command -v ddev >/dev/null 2>&1; then
    echo "ddev ist nicht verfügbar; DB-Umlaut-Check kann nicht ausgeführt werden."
    exit 1
  fi

  local sql matches
  sql="$(
    cat <<SQL
SELECT hits FROM (
  SELECT CONCAT('tt_content.uid=', uid, ' header: ', LEFT(header, 160)) AS hits
    FROM tt_content
    WHERE header REGEXP '${DB_PATTERN}'
  UNION ALL
  SELECT CONCAT('tt_content.uid=', uid, ' subheader: ', LEFT(subheader, 160)) AS hits
    FROM tt_content
    WHERE subheader REGEXP '${DB_PATTERN}'
  UNION ALL
  SELECT CONCAT('tt_content.uid=', uid, ' bodytext: ', LEFT(REPLACE(REPLACE(bodytext, CHAR(10), ' '), CHAR(13), ' '), 160)) AS hits
    FROM tt_content
    WHERE bodytext REGEXP '${DB_PATTERN}'
  UNION ALL
  SELECT CONCAT('tx_themecamino_list_item.uid=', uid, ' header: ', LEFT(header, 160)) AS hits
    FROM tx_themecamino_list_item
    WHERE header REGEXP '${DB_PATTERN}'
  UNION ALL
  SELECT CONCAT('tx_themecamino_list_item.uid=', uid, ' link_label: ', LEFT(link_label, 160)) AS hits
    FROM tx_themecamino_list_item
    WHERE link_label REGEXP '${DB_PATTERN}'
  UNION ALL
  SELECT CONCAT('tx_themecamino_list_item.uid=', uid, ' text: ', LEFT(REPLACE(REPLACE(text, CHAR(10), ' '), CHAR(13), ' '), 160)) AS hits
    FROM tx_themecamino_list_item
    WHERE text REGEXP '${DB_PATTERN}'
) AS visible_umlaut_hits
LIMIT 50;
SQL
  )"

  matches="$(ddev mysql -N -B -e "${sql}")"

  if [ -n "${matches}" ]; then
    echo "ASCII-Umlaut-Schreibweisen in sichtbaren TYPO3-Content-Feldern gefunden:"
    echo "${matches}"
    echo
    echo "Bitte Content korrigieren, bevor die Datenbank nach Production gepusht wird."
    exit 1
  fi

  echo "✓ DB-Umlaut-Check OK"
}

case "${MODE}" in
  --source)
    check_source
    ;;
  --db)
    check_db
    ;;
  --all)
    check_source
    check_db
    ;;
  -h|--help)
    usage
    ;;
  *)
    usage
    exit 2
    ;;
esac
