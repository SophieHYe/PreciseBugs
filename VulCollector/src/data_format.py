from dataclasses import dataclass
from typing import Any, Sequence


@dataclass
class BugEntry:
    buggy_code: Sequence[str]
    fixing_code: Sequence[str]
    filenames: Sequence[str]
    buggy_code_start_loc: Sequence[int]
    buggy_code_end_loc: Sequence[int]
    fixing_code_start_loc: Sequence[int]
    fixing_code_end_loc: Sequence[int]
    type: str
    message: str
    other: Any