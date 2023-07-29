import argparse
import collections
import json
import tqdm

from pathlib import Path
from typing import Sequence


def get_most_common_extension(filenames: Sequence[str]) -> str:
    all_filename_extensions = []
    for filename in filenames:
        all_filename_extensions.append(Path(filename).suffix)
    if not all_filename_extensions:
        return ''
    return collections.Counter(all_filename_extensions).most_common(1)[0][0]


def main():
    parser = argparse.ArgumentParser(
        description='Compute the data statistics about the final dataset.')
    parser.add_argument('--data_dir', action="store", required=True,
                        dest='data_dir', help="The data directory")
    parser.add_argument('--output_dir', action="store", required=True,
                        dest='output_dir', help="The statistic output directory")
    args = parser.parse_args()

    data_dir = Path(args.data_dir).resolve()
    output_dir = Path(args.output_dir).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)

    all_projects = []
    all_extensions = []

    extension_to_project = {}
    for bug_entry_file in tqdm.tqdm(data_dir.rglob('*.json'), desc='Reading BugEntry files'):
        with bug_entry_file.open() as f:
            bug_entry = json.load(f)

        github_url = bug_entry['other']['github_commit_url']
        github_project_name = '/'.join(github_url.split('/')[3:5])
        all_projects.append(github_project_name)

        most_common_extension = get_most_common_extension(bug_entry['filenames'])
        all_extensions.append(most_common_extension)

        if most_common_extension not in extension_to_project:
            extension_to_project[most_common_extension] = set(github_project_name)
        else:
            extension_to_project[most_common_extension].add(github_project_name)

    project_counter = collections.Counter(all_projects)
    projects_counts = {
        project: project_counter[project]
        for project in project_counter
    }
    projects_counts = dict(sorted(projects_counts.items(), key=lambda item: item[1], reverse=True))
    project_count_file = output_dir / 'nvd_project_count.json'
    with project_count_file.open('w', encoding='utf-8') as f:
        json.dump(projects_counts, f)
        f.write('\n')

    extension_counter = collections.Counter(all_extensions)
    extension_counts = {
        extension: extension_counter[extension]
        for extension in extension_counter
    }
    extension_counts = dict(sorted(extension_counts.items(), key=lambda item: item[1], reverse=True))
    extension_count_file = output_dir / 'nvd_extension_count.json'
    with extension_count_file.open('w', encoding='utf-8') as f:
        json.dump(extension_counts, f)
        f.write('\n')

    extension_to_project_count = {
        k: len(v)
        for k, v in extension_to_project.items()
    }
    extension_to_project_count = dict(sorted(extension_to_project_count.items(), key=lambda item: item[1], reverse=True))
    extension_to_project_count_file = output_dir / 'nvd_extension_to_project_count.json'
    with extension_to_project_count_file.open('w', encoding='utf-8') as f:
        json.dump(extension_to_project_count, f)
        f.write('\n')



if __name__ == "__main__":
    main()