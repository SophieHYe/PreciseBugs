import argparse
import json
import re
import requests
import tqdm

from pathlib import Path
from requests.adapters import HTTPAdapter, Retry

NVD_BASE_URL = "https://services.nvd.nist.gov/rest/json/cves/2.0"
RESULT_PER_PAGE = 2000


def download_cve(output_dir: Path, last_start_index: int, total_result: int, session: requests.Session):
    for start_index in tqdm.tqdm(range(last_start_index, total_result, RESULT_PER_PAGE), desc='Downloading json files'):
        file_end_index = min(start_index+RESULT_PER_PAGE, total_result)
        output_file = output_dir / f'{start_index}_{file_end_index}.jsonl'

        response = session.get(NVD_BASE_URL, params={'resultsPerPage': RESULT_PER_PAGE, 'startIndex': start_index})
        response.raise_for_status()
        with output_file.open('w', encoding='utf-8') as f:
            for vulnerability in response.json()['vulnerabilities']:
                json.dump(vulnerability, f)
                f.write('\n')


def check_existing_files(output_dir: Path) -> int:
    max_last_start_index = 0
    pattern = re.compile('[0-9]+_([0-9]+)\.jsonl')
    for file in output_dir.iterdir():
        pattern_result = pattern.findall(file.name)
        if pattern_result:
            max_last_start_index = max(max_last_start_index, int(pattern_result[0]))
    return max_last_start_index


def main():
    parser = argparse.ArgumentParser(
        description='Download CVE metadata from NVD.')
    parser.add_argument('--output_dir', action="store", required=True,
                        dest='output_dir', help="The output directory")
    args = parser.parse_args()

    output_dir = Path(args.output_dir).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)

    session = requests.Session()
    retries = Retry(total=3,
                    backoff_factor=5,
                    status_forcelist=[403, 429, 500, 502, 503, 504])
    session.mount('https://', HTTPAdapter(max_retries=retries))

    response = session.get(NVD_BASE_URL)
    total_result = response.json()['totalResults']

    max_last_start_index = check_existing_files(output_dir)

    download_cve(output_dir, max_last_start_index, total_result, session)


if __name__ == "__main__":
    main()