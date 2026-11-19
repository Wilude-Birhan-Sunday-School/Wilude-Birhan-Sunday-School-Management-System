from collections import defaultdict
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
SQL_DIR = ROOT / "sql"

LEGACY_DUPLICATES = {
    "081": (
        "081_acm_office_model.sql",
        "081_telegram_registration_phase2_interview.sql",
    ),
    "082": (
        "082_acm_activation_ceremony.sql",
        "082_telegram_registration_corrections.sql",
    ),
    "091": (
        "091_acm_staff_enrollment_requests.sql",
        "091_telegram_v2_state_machine.sql",
    ),
}


def duplicate_migration_numbers(
    sql_dir: Path = SQL_DIR,
) -> dict[str, tuple[str, ...]]:
    files_by_number: dict[str, list[str]] = defaultdict(list)
    for path in sorted(sql_dir.glob("*.sql")):
        number = path.name.split("_", 1)[0]
        if number.isdigit():
            files_by_number[number].append(path.name)
    return {
        number: tuple(files)
        for number, files in files_by_number.items()
        if len(files) > 1
    }


def unapproved_duplicate_migration_numbers(
    sql_dir: Path = SQL_DIR,
) -> dict[str, tuple[str, ...]]:
    return {
        number: files
        for number, files in duplicate_migration_numbers(sql_dir).items()
        if LEGACY_DUPLICATES.get(number) != files
    }


def main() -> int:
    duplicates = duplicate_migration_numbers()
    unapproved = unapproved_duplicate_migration_numbers()
    if unapproved:
        for number, files in sorted(unapproved.items()):
            print(
                f"::error::Unapproved duplicate migration number {number}: "
                f"{', '.join(files)}",
                file=sys.stderr,
            )
        return 1

    if duplicates:
        print(
            "Only documented legacy migration-number collisions remain: "
            + ", ".join(sorted(duplicates))
        )
    else:
        print("Migration numbers are unique.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
