import argparse
import json
import re
import tqdm

from pathlib import Path
from typing import Tuple


github_url_commit_pattern = re.compile('github\.com\/(.*)\/(.*)\/commit\/([0-9a-f]{40})')


def filter_nvd_vulnerabilities(data_dir: Path, output_dir: Path) -> Tuple[int, int]:
    total_count = 0
    kept_count = 0

    for input_data_file in tqdm.tqdm(list(data_dir.glob('*.jsonl')), desc='Filter vulnerabilities if they have patch on GitHub'):
        output_data_file = output_dir / input_data_file.name
        with input_data_file.open('r', encoding='utf-8') as input_file, output_data_file.open('w', encoding='utf-8') as output_file:
            for line in input_file:
                total_count += 1

                nvd_vul = json.loads(line)
                found_github_patch = False
                for reference in nvd_vul['cve']['references']:
                    if github_url_commit_pattern.findall(reference['url']) and 'tags' in reference and 'Patch' in reference['tags']:
                        nvd_vul['github_commit_url'] = reference['url']
                        found_github_patch = True
                        break

                if found_github_patch:
                    kept_count += 1
                    json.dump(nvd_vul, output_file)
                    output_file.write('\n')

    
    return kept_count, total_count


def main():
    parser = argparse.ArgumentParser(
        description='Filter NVD vulnerabilities based on if they have the corresponding patch on GitHub.')
    parser.add_argument('--data_dir', action="store", required=True,
                        dest='data_dir', help="The NVD jsonl data directory")
    parser.add_argument('--output_dir', action="store", required=True,
                        dest='output_dir', help="The output directory")
    args = parser.parse_args()

    data_dir = Path(args.data_dir).resolve()
    output_dir = Path(args.output_dir).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)

    kept_count, total_count = filter_nvd_vulnerabilities(data_dir, output_dir)
    print(f'Kept {kept_count} out of {total_count} NVD vulnerabilities.')


if __name__ == "__main__":
    main()