import argparse
import dataclasses
import difflib
import getpass
import github
import json
import logging
import re
import requests
import tqdm

from pathlib import Path
from requests.adapters import HTTPAdapter, Retry
from typing import Mapping, Sequence, Tuple
from unidiff import PatchSet

import data_format

import sys


github_url_commit_pattern = re.compile('github\.com\/(.*)\/(.*)\/commit\/([0-9a-f]{40})')
JS_DELIVR_BASE_URL = 'https://cdn.jsdelivr.net/gh'


def get_commit_metadata(g: github.Github, username: str, repo_name: str, commit_id: str) -> Tuple[str, Sequence[str]]:
    repo = g.get_repo(f'{username}/{repo_name}')
    commit = repo.get_commit(sha=commit_id)

    parent_commit_id = commit.parents[0].sha
    changed_filenames = [
        file.filename
        for file in commit.files
    ]
    return parent_commit_id, changed_filenames


def get_source_code(session: requests.Session, username: str, repo_name: str, commit_id: str,
                    parent_commit_id: str, changed_filenames: Sequence[str]):
    pre_version_file_contents = []
    post_version_file_contents = []

    for changed_filename in changed_filenames:
        pre_version_url = f'{JS_DELIVR_BASE_URL}/{username}/{repo_name}@{parent_commit_id}/{changed_filename}'
        post_version_url = f'{JS_DELIVR_BASE_URL}/{username}/{repo_name}@{commit_id}/{changed_filename}'

        pre_version_result = session.get(pre_version_url)
        post_version_reuslt = session.get(post_version_url)

        pre_version_result.raise_for_status()
        post_version_reuslt.raise_for_status()

        pre_version_file_contents.append(pre_version_result.text)
        post_version_file_contents.append(post_version_reuslt.text)

    return pre_version_file_contents, post_version_file_contents


def get_cwe_id(nvd_vul: Mapping[str, any]) -> str:
    for weakness in nvd_vul['cve']['weaknesses']:
        for desc in weakness['description']:
            if desc['lang'] == 'en':
                return desc['value']
    return 'NVD-CWE-noinfo'


def get_cve_description(nvd_vul: Mapping[str, any]) -> str:
    for description in nvd_vul['cve']['descriptions']:
        if description['lang'] == 'en':
            return description['value']
    return ''


def create_bug_entry(nvd_vul: Mapping[str, any], pre_version_file_contents: Sequence[str], post_version_file_contents: Sequence[str],
                     changed_filenames: Sequence[str]) -> data_format.BugEntry:
    buggy_code_start_loc = []
    buggy_code_end_loc = []
    fixing_code_start_loc = []
    fixing_code_end_loc = []

    for pre_file_content, post_file_content, filename in zip(pre_version_file_contents, post_version_file_contents, changed_filenames):
        file_diff = difflib.unified_diff(
            pre_file_content.splitlines(True), post_file_content.splitlines(True), fromfile=filename, tofile=filename, n=0)
        patch = PatchSet(file_diff)

        min_source_line_no = float('inf')
        max_source_line_no = 0
        min_target_line_no = float('inf')
        max_target_line_no = 0

        if not patch:
            raise ValueError(f'No diff between files')

        for hunk in patch[0]:
            min_source_line_no = min(min_source_line_no, hunk.source_start)
            max_source_line_no = max(max_source_line_no, hunk.source_start+hunk.source_length)
            min_target_line_no = min(min_target_line_no, hunk.target_start)
            max_target_line_no = max(max_target_line_no, hunk.target_start+hunk.target_length)

        buggy_code_start_loc.append(min_source_line_no)
        buggy_code_end_loc.append(max_source_line_no)
        fixing_code_start_loc.append(min_target_line_no)
        fixing_code_end_loc.append(max_target_line_no)

    cwe_id = get_cwe_id(nvd_vul)
    description = get_cve_description(nvd_vul)

    return data_format.BugEntry(
        buggy_code=pre_version_file_contents,
        fixing_code=post_version_file_contents,
        buggy_code_start_loc=buggy_code_start_loc,
        buggy_code_end_loc=buggy_code_end_loc,
        fixing_code_start_loc=fixing_code_start_loc,
        fixing_code_end_loc=fixing_code_end_loc,
        type=cwe_id,
        message=description,
        other=nvd_vul,
    )


def process_nvd_vul(g: github.Github, data_dir: Path, output_dir: Path, session: requests.Session):
    for data_file in tqdm.tqdm(list(data_dir.glob('*.jsonl')), desc='Processing NVD vulnerabilities'):
        with data_file.open('r', encoding='utf-8') as f:
            for line in f:
                nvd_vul = json.loads(line)

                output_file = output_dir / nvd_vul['cve']['id'] / 'BugEntry.json'
                if output_file.exists():
                    continue

                github_commit_url = nvd_vul['github_commit_url']

                result = github_url_commit_pattern.search(github_commit_url)
                if not result:
                    logging.warning(f"{nvd_vul['cve']['id']} does not a patch on github, have you run filter_nvd_vul.py?")

                username, repo_name, commit_id = result.groups()

                try:
                    parent_commit_id, changed_filenames = get_commit_metadata(
                        g, username, repo_name, commit_id)
                except github.GithubException as e:
                    logging.warning(f"Failed to get commit metadata for {nvd_vul['cve']['id']}, error trace: \n{e}")
                    continue
                except IndexError as e:
                    logging.warning(f"IndexError for {nvd_vul['cve']['id']}, error trace: \n{e}")
                    continue
                

                try:
                    pre_version_file_contents, post_version_file_contents = get_source_code(
                        session, username, repo_name, commit_id, parent_commit_id,
                        changed_filenames)
                except requests.exceptions.HTTPError as e:
                    logging.warning(f"Failed to get source code for {nvd_vul['cve']['id']}, error trace: \n{e}")
                    continue

                
                try:
                    bug_entry = create_bug_entry(nvd_vul, pre_version_file_contents, post_version_file_contents, changed_filenames)
                except ValueError as e:
                    logging.warning(f"Failed to diff two files for {nvd_vul['cve']['id']}, error trace: \n{e}")
                    continue

                output_file.parent.mkdir(parents=True, exist_ok=True)
                with output_file.open('w', encoding='utf-8') as o:
                    json.dump(dataclasses.asdict(bug_entry), o)



def main():
    parser = argparse.ArgumentParser(
        description='Get the GitHub source code and output data in the final format.')
    parser.add_argument('--data_dir', action="store", required=True,
                        dest='data_dir', help="The filtered NVD jsonl data directory")
    parser.add_argument('--output_dir', action="store", required=True,
                        dest='output_dir', help="The output directory")
    args = parser.parse_args()

    data_dir = Path(args.data_dir).resolve()
    output_dir = Path(args.output_dir).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)

    access_token = getpass.getpass(prompt='Access token for GitHub API: ')

    g = github.Github(access_token, retry=Retry(3, backoff_factor=10))

    session = requests.Session()
    retries = Retry(total=3,
                    backoff_factor=5,
                    status_forcelist=[429, 500, 502, 503, 504])
    session.mount('https://', HTTPAdapter(max_retries=retries))

    process_nvd_vul(g, data_dir, output_dir, session)


if __name__ == "__main__":
    main()