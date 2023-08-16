import json
import os.path

from aiohttp import web, ClientError
import mock
import pytest
import ruamel.yaml as yaml

from pollbot import __version__ as pollbot_version, HTTP_API_VERSION, PRODUCTS
from pollbot.app import get_app
from pollbot.middlewares import NO_CACHE_ENDPOINTS
from pollbot.exceptions import TaskError
from pollbot.tasks.buildhub import get_build_ids_for_version
from pollbot.views.release import status_response
from pollbot.utils import Status

HERE = os.path.dirname(__file__)


@pytest.fixture
def cli(loop, test_client):
    async def error403(request):
        raise web.HTTPForbidden()

    async def error404(request):
        return web.HTTPNotFound()

    async def error(request):
        raise ValueError()

    app = get_app(loop=loop)
    app.router.add_get('/error', error)
    app.router.add_get('/error-403', error403)
    app.router.add_get('/error-404', error404)
    return loop.run_until_complete(test_client(app))


async def check_response(cli, url, *, status=200, body=None, method="get", **kwargs):
    resp = await getattr(cli, method)(url, **kwargs)
    assert resp.status == status
    text = json.dumps(body)
    text = text.replace('http://localhost/', '{}://{}:{}/'.format(
        resp.url.scheme, resp.url.host, resp.url.port))
    if body is not None:
        assert await resp.json() == json.loads(text)
    return resp


async def test_home_redirects_to_v1(cli):
    resp = await check_response(cli, "/", status=302, allow_redirects=False)
    assert resp.headers['Location'] == "/v1/"


async def test_v1_redirects_to_v1_slash(cli):
    resp = await check_response(cli, "/v1", status=302, allow_redirects=False)
    assert resp.headers['Location'] == "/v1/"


async def test_redirects_trailing_slashes(cli):
    resp = await check_response(cli, "/v1/firefox/54.0/", status=302, allow_redirects=False)
    assert resp.headers['Location'] == "/v1/firefox/54.0"


async def test_redirects_strip_leading_slashes(cli):
    resp = await check_response(cli, "//page/", status=302, allow_redirects=False)
    assert resp.headers['Location'] == "/page"


async def check_yaml_resource(cli, url, filename, **kwargs):
    with open(os.path.join(HERE, "..", "pollbot", filename)) as stream:
        content = yaml.safe_load(stream)
    resp = await cli.get(url, headers={"Host": "127.0.0.1"})
    content.update(**kwargs)
    assert await resp.json() == content


async def test_oas_spec(cli):
    await check_yaml_resource(cli, "/v1/__api__", "api.yaml", host="127.0.0.1")


async def test_contribute_redirect(cli):
    resp = await check_response(cli, "/contribute.json", status=302, allow_redirects=False)
    assert resp.headers['Location'] == "/v1/contribute.json"


async def test_contribute_json(cli):
    await check_yaml_resource(cli, "/v1/contribute.json", "contribute.yaml")


async def test_home_body(cli):
    await check_response(cli, "/v1/", body={
        "project_name": "pollbot",
        "project_version": pollbot_version,
        "url": "https://github.com/mozilla/PollBot",
        "http_api_version": HTTP_API_VERSION,
        "docs": "http://127.0.0.1/v1/api/doc/",
        "products": PRODUCTS
    }, headers={"Host": "127.0.0.1"})


async def test_status_response_handle_task_errors(cli):
    async def error_task(product, version):
        raise TaskError('Error message')
    error_endpoint = status_response(error_task)
    request = mock.MagicMock()
    request.match_info = {"product": "firefox", "version": "57.0"}
    resp = await error_endpoint(request)
    assert json.loads(resp.body.decode()) == {
        "status": Status.ERROR.value,
        "message": "Error message",
    }


async def test_status_response_handle_task_errors_with_links(cli):
    async def error_task(product, version):
        raise TaskError('Error message', url='http://www.perdu.com/')
    error_endpoint = status_response(error_task)
    request = mock.MagicMock()
    request.match_info = {"product": "firefox", "version": "57.0"}
    resp = await error_endpoint(request)
    assert json.loads(resp.body.decode()) == {
        "status": Status.ERROR.value,
        "message": "Error message",
        "link": "http://www.perdu.com/"
    }


async def test_status_response_handle_client_errors(cli):
    async def error_task(product, version):
        raise ClientError('Error message')
    error_endpoint = status_response(error_task)
    request = mock.MagicMock()
    request.match_info = {"product": "firefox", "version": "57.0"}
    resp = await error_endpoint(request)
    assert json.loads(resp.body.decode()) == {
        "status": Status.ERROR.value,
        "message": "Error message",
    }


async def test_status_response_validates_product_name(cli):
    async def dummy_task(product, version):
        return True
    error_endpoint = status_response(dummy_task)
    request = mock.MagicMock()
    request.match_info = {"product": "invalid-product", "version": "57.0"}
    resp = await error_endpoint(request)
    assert resp.status == 404
    assert json.loads(resp.body.decode()) == {
        "status": 404,
        "message": "Invalid product: invalid-product not in ('firefox', "
                   "'devedition', 'thunderbird')",
    }


async def test_status_response_validates_version(cli):
    async def dummy_task(product, version):
        return True
    error_endpoint = status_response(dummy_task)
    request = mock.MagicMock()
    request.match_info = {"product": "firefox", "version": "invalid-version"}
    resp = await error_endpoint(request)
    assert resp.status == 404
    assert json.loads(resp.body.decode()) == {
        "status": 404,
        "message": "Invalid version number: invalid-version",
    }


async def test_status_response_validates_devedition_version(cli):
    async def dummy_task(product, version):
        return True
    error_endpoint = status_response(dummy_task)
    request = mock.MagicMock()
    request.match_info = {"product": "devedition", "version": "58.0"}
    resp = await error_endpoint(request)
    assert resp.status == 404
    assert json.loads(resp.body.decode()) == {
        "status": 404,
        "message": "Invalid version number for devedition: 58.0",
    }


async def test_get_releases_response_validates_product_name(cli):
    await check_response(cli, "/v1/invalid-product", body={
        "status": 404,
        "message": "Invalid product: invalid-product not in ('firefox', "
                   "'devedition', 'thunderbird')"
    }, status=404)


async def test_get_releases_response_validates_version(cli):
    await check_response(cli, "/v1/firefox/invalid-version", body={
        "status": 404,
        "message": "Invalid version number: invalid-version"
    }, status=404)


async def test_403_errors_are_json_responses(cli):
    await check_response(cli, "/error-403", body={
        "status": 403,
        "message": "Forbidden"
    }, status=403)


async def test_404_pages_are_json_responses(cli):
    await check_response(cli, "/not-found", body={
        "status": 404,
        "message": "Page '/not-found' not found"
    }, status=404)


async def test_handle_views_that_return_404_pages_are_json_responses(cli):
    await check_response(cli, "/error-404", body={
        "status": 404,
        "message": "Page '/error-404' not found"
    }, status=404)


async def test_500_pages_are_json_responses(cli):
    await check_response(cli, "/error", body={
        "status": 503,
        "message": "Service currently unavailable"
    }, status=503)


async def test_get_checks_for_nightly(cli):
    await check_response(cli, "/v1/firefox/57.0a1", body={
        "product": "firefox",
        "version": "57.0a1",
        "channel": "nightly",
        "checks": [
            {"url": "http://localhost/v1/firefox/57.0a1/archive", "title": "Archive Release",
             "actionable": True},
            {"url": "http://localhost/v1/firefox/57.0a1/balrog-rules",
             "title": "Balrog update rules", "actionable": True},
            {"url": "http://localhost/v1/firefox/57.0a1/bouncer",
             "title": "Bouncer", "actionable": True},
            {"url": "http://localhost/v1/firefox/57.0a1/buildhub",
             "title": "Buildhub release info", "actionable": True},
            {"url": "http://localhost/v1/firefox/57.0a1/bedrock/download-links",
             "title": "Download links", "actionable": True},
            {"url": "http://localhost/v1/firefox/57.0a1/product-details",
             "title": "Product details", "actionable": True},
            {"url": "http://localhost/v1/firefox/57.0a1/bedrock/release-notes",
             "title": "Release notes", "actionable": True},
            {"url": "http://localhost/v1/firefox/57.0a1/telemetry/main-summary-uptake",
             "title": "Telemetry Main Summary Uptake (24h latency)", "actionable": False},
        ]
    })


async def test_get_checks_for_beta(cli):
    await check_response(cli, "/v1/firefox/56.0b6", body={
        "product": "firefox",
        "version": "56.0b6",
        "channel": "beta",
        "checks": [
            {"url": "http://localhost/v1/firefox/56.0b6/archive", "title": "Archive Release",
             "actionable": True},
            {"url": "http://localhost/v1/firefox/56.0b6/balrog-rules",
             "title": "Balrog update rules", "actionable": True},
            {"url": "http://localhost/v1/firefox/56.0b6/bouncer",
             "title": "Bouncer", "actionable": True},
            {"url": "http://localhost/v1/firefox/56.0b6/buildhub",
             "title": "Buildhub release info", "actionable": True},
            {"url": "http://localhost/v1/firefox/56.0b6/product-details"
             "/devedition-beta-versions-matches", "actionable": True,
             "title": "Devedition and Beta versions matches"},
            {"url": "http://localhost/v1/firefox/56.0b6/bedrock/download-links",
             "title": "Download links", "actionable": True},
            {"url": "http://localhost/v1/firefox/56.0b6/archive/partner-repacks",
             "title": "Partner repacks", "actionable": True},
            {"url": "http://localhost/v1/firefox/56.0b6/product-details",
             "title": "Product details", "actionable": True},
            {"url": "http://localhost/v1/firefox/56.0b6/bedrock/release-notes",
             "title": "Release notes", "actionable": True},
            {"url": "http://localhost/v1/firefox/56.0b6/telemetry/main-summary-uptake",
             "title": "Telemetry Main Summary Uptake (24h latency)", "actionable": False},

        ]
    })


async def test_get_checks_for_devedition(cli):
    await check_response(cli, "/v1/devedition/56.0b6", body={
        "product": "devedition",
        "version": "56.0b6",
        "channel": "aurora",
        "checks": [
            {"url": "http://localhost/v1/devedition/56.0b6/archive",
             "title": "Archive Release", "actionable": True},
            {"url": "http://localhost/v1/devedition/56.0b6/balrog-rules",
             "title": "Balrog update rules", "actionable": True},
            {"url": "http://localhost/v1/devedition/56.0b6/bouncer",
             "title": "Bouncer", "actionable": True},
            {"url": "http://localhost/v1/devedition/56.0b6/buildhub",
             "title": "Buildhub release info", "actionable": True},
            {"url": "http://localhost/v1/devedition/56.0b6/product-details"
             "/devedition-beta-versions-matches", "actionable": True,
             "title": "Devedition and Beta versions matches"},
            {"url": "http://localhost/v1/devedition/56.0b6/bedrock/download-links",
             "title": "Download links", "actionable": True},
            {"url": "http://localhost/v1/devedition/56.0b6/product-details",
             "title": "Product details", "actionable": True},
            {"url": "http://localhost/v1/devedition/56.0b6/bedrock/release-notes",
             "title": "Release notes", "actionable": True},
            {"url": "http://localhost/v1/devedition/56.0b6/telemetry/main-summary-uptake",
             "title": "Telemetry Main Summary Uptake (24h latency)", "actionable": False},
        ]
    })


async def test_get_checks_for_candidates(cli):
    await check_response(cli, "/v1/firefox/57.0rc6", body={
        "product": "firefox",
        "version": "57.0rc6",
        "channel": "candidate",
        "checks": [
            {"url": "http://localhost/v1/firefox/57.0rc6/archive", "title": "Archive Release",
             "actionable": True},
            {"url": "http://localhost/v1/firefox/57.0rc6/buildhub",
             "title": "Buildhub release info", "actionable": True},
            {'title': 'Partner repacks', "actionable": True,
             'url': 'http://localhost/v1/firefox/57.0rc6/archive/partner-repacks'},
        ]
    })


async def test_get_checks_for_release(cli):
    await check_response(cli, "/v1/firefox/54.0", body={
        "product": "firefox",
        "version": "54.0",
        "channel": "release",
        "checks": [
            {"url": "http://localhost/v1/firefox/54.0/archive", "title": "Archive Release",
             "actionable": True},
            {"url": "http://localhost/v1/firefox/54.0/balrog-rules",
             "title": "Balrog update rules", "actionable": True},
            {"url": "http://localhost/v1/firefox/54.0/bouncer",
             "title": "Bouncer", "actionable": True},
            {"url": "http://localhost/v1/firefox/54.0/buildhub",
             "title": "Buildhub release info", "actionable": True},
            {"url": "http://localhost/v1/firefox/54.0/bedrock/download-links",
             "title": "Download links", "actionable": True},
            {"url": "http://localhost/v1/firefox/54.0/archive/partner-repacks",
             "title": "Partner repacks", "actionable": True},
            {"url": "http://localhost/v1/firefox/54.0/product-details",
             "title": "Product details", "actionable": True},
            {"url": "http://localhost/v1/firefox/54.0/bedrock/release-notes",
             "title": "Release notes", "actionable": True},
            {"url": "http://localhost/v1/firefox/54.0/bedrock/security-advisories",
             "title": "Security advisories", "actionable": True},
            {'title': 'Telemetry Main Summary Uptake (24h latency)', "actionable": False,
             'url': 'http://localhost/v1/firefox/54.0/telemetry/main-summary-uptake'},
        ]
    })


async def test_get_checks_for_esr(cli):
    await check_response(cli, "/v1/firefox/52.3.0esr", body={
        "product": "firefox",
        "version": "52.3.0esr",
        "channel": "esr",
        "checks": [
            {"url": "http://localhost/v1/firefox/52.3.0esr/archive", "title": "Archive Release",
             "actionable": True},
            {"url": "http://localhost/v1/firefox/52.3.0esr/balrog-rules",
             "title": "Balrog update rules", "actionable": True},
            {"url": "http://localhost/v1/firefox/52.3.0esr/bouncer",
             "title": "Bouncer", "actionable": True},
            {"url": "http://localhost/v1/firefox/52.3.0esr/buildhub",
             "title": "Buildhub release info", "actionable": True},
            {"url": "http://localhost/v1/firefox/52.3.0esr/bedrock/download-links",
             "title": "Download links", "actionable": True},
            {"url": "http://localhost/v1/firefox/52.3.0esr/product-details",
             "title": "Product details", "actionable": True},
            {"url": "http://localhost/v1/firefox/52.3.0esr/bedrock/release-notes",
             "title": "Release notes", "actionable": True},
            {"url": "http://localhost/v1/firefox/52.3.0esr/bedrock/security-advisories",
             "title": "Security advisories", "actionable": True},
            {"url": "http://localhost/v1/firefox/52.3.0esr/telemetry/main-summary-uptake",
             "title": "Telemetry Main Summary Uptake (24h latency)", "actionable": False},
        ]
    })


async def test_get_checks_response_validates_product_name(cli):
    await check_response(cli, "/v1/invalid-product/56.0", body={
        "status": 404,
        "message": "Invalid product: invalid-product not in ('firefox', "
                   "'devedition', 'thunderbird')"
    }, status=404)


# These are currently functional tests.

async def test_nightly_archive(cli):
    resp = await check_response(cli, "/v1/firefox/58.0a1/archive")
    body = await resp.json()
    assert 'firefox/nightly/latest-mozilla-central-l10n' in body['message']
    assert body['link'] == ("https://archive.mozilla.org/pub/firefox/nightly/"
                            "latest-mozilla-central-l10n/")
    assert body['status'] in (Status.EXISTS.value, Status.INCOMPLETE.value, Status.MISSING.value)


async def test_release_archive(cli):
    await check_response(cli, "/v1/firefox/54.0/archive", body={
        "status": Status.EXISTS.value,
        "message": "The archive exists at https://archive.mozilla.org/pub/firefox/releases/54.0/ "
        "and all 94 locales are present for all platforms "
        "(linux-i686, linux-x86_64, mac, win32, win64)",
        "link": "https://archive.mozilla.org/pub/firefox/releases/54.0/"
    })


async def test_candidate_archive(cli):
    await check_response(cli, "/v1/firefox/56.0.2rc1/archive", body={
        "status": Status.EXISTS.value,
        "message": "The archive exists at "
        "https://archive.mozilla.org/pub/firefox/candidates/56.0.2-candidates/build1/ "
        "and all 95 locales are present for all platforms "
        "(linux-i686, linux-x86_64, mac, win32, win64)",
        "link": "https://archive.mozilla.org/pub/firefox/candidates/56.0.2-candidates/build1/"
    })


async def test_candidate_archive_build(cli):
    await check_response(cli, "/v1/firefox/56.0.2build1/archive", body={
        "status": Status.EXISTS.value,
        "message": "The archive exists at "
        "https://archive.mozilla.org/pub/firefox/candidates/56.0.2-candidates/build1/ "
        "and all 95 locales are present for all platforms "
        "(linux-i686, linux-x86_64, mac, win32, win64)",
        "link": "https://archive.mozilla.org/pub/firefox/candidates/56.0.2-candidates/build1/"
    })


async def test_beta_archive(cli):
    await check_response(cli, "/v1/firefox/56.0b10/archive", body={
        "status": Status.EXISTS.value,
        "message": "The archive exists at https://archive.mozilla.org/pub/firefox/releases/56.0b10"
        "/ and all 95 locales are present for all platforms "
        "(linux-i686, linux-x86_64, mac, win32, win64)",
        "link": "https://archive.mozilla.org/pub/firefox/releases/56.0b10/"
    })


async def test_devedition_archive(cli):
    await check_response(cli, "/v1/devedition/59.0b5/archive", body={
        "status": Status.EXISTS.value,
        "message": "The archive exists at https://archive.mozilla.org/pub/devedition/releases"
        "/59.0b5/ and all 97 locales are present for all platforms "
        "(linux-i686, linux-x86_64, mac, win32, win64)",
        "link": "https://archive.mozilla.org/pub/devedition/releases/59.0b5/"
    })


async def test_esr_archive(cli):
    await check_response(cli, "/v1/firefox/52.3.0esr/archive", body={
        "status": Status.EXISTS.value,
        "message": "The archive exists at https://archive.mozilla.org/pub/firefox/releases/"
        "52.3.0esr/ and all 92 locales are present for all platforms "
        "(linux-i686, linux-x86_64, mac, win32, win64)",
        "link": "https://archive.mozilla.org/pub/firefox/releases/52.3.0esr/"
    })


async def test_release_partner_repacks(cli):
    await check_response(cli, "/v1/firefox/54.0/archive/partner-repacks", body={
        "status": Status.EXISTS.value,
        "message": "Partner-repacks found in https://archive.mozilla.org/pub/"
        "firefox/candidates/54.0-candidates/build3/",
        "link": "https://archive.mozilla.org/pub/firefox/candidates/54.0-candidates/build3/"
    })


async def test_candidate_partner_repacks_build(cli):
    await check_response(cli, "/v1/firefox/56.0.2build1/archive/partner-repacks", body={
        "status": Status.EXISTS.value,
        "message": "Partner-repacks found in https://archive.mozilla.org/pub/"
        "firefox/candidates/56.0.2-candidates/build1/",
        "link": "https://archive.mozilla.org/pub/firefox/candidates/56.0.2-candidates/build1/"
    })


async def test_candidate_partner_repacks(cli):
    await check_response(cli, "/v1/firefox/56.0.2rc1/archive/partner-repacks", body={
        "status": Status.EXISTS.value,
        "message": "Partner-repacks found in https://archive.mozilla.org/pub/"
        "firefox/candidates/56.0.2-candidates/build1/",
        "link": "https://archive.mozilla.org/pub/firefox/candidates/56.0.2-candidates/build1/"
    })


async def test_beta_partner_repacks(cli):
    await check_response(cli, "/v1/firefox/56.0b10/archive/partner-repacks", body={
        "status": Status.EXISTS.value,
        "message": "Partner-repacks found in https://archive.mozilla.org/pub/"
        "firefox/candidates/56.0b10-candidates/build1/",
        "link": "https://archive.mozilla.org/pub/firefox/candidates/56.0b10-candidates/build1/"
    })


async def test_release_balrog_rules(cli):
    resp = await check_response(cli, "/v1/firefox/54.0/balrog-rules")
    body = await resp.json()
    assert body["status"] in (Status.EXISTS.value, Status.INCOMPLETE.value)
    assert "Balrog rule has been updated" in body["message"]
    assert body["link"] == "https://aus-api.mozilla.org/api/v1/rules/firefox-release"


async def test_release_buildhub(cli):
    resp = await check_response(cli, "/v1/firefox/54.0/buildhub")
    body = await resp.json()
    assert body["status"] == Status.EXISTS.value
    assert "Build IDs for this release: 20170608175746, 20170608105825" == body["message"]
    assert body["link"] == ("https://buildhub.moz.tools/"
                            "?versions[0]=54.0&products[0]=firefox&channel[0]=release")


async def test_candidates_buildhub(cli):
    resp = await check_response(cli, "/v1/firefox/56.0.1rc2/buildhub")
    body = await resp.json()
    assert body["status"] == Status.EXISTS.value
    assert "Build IDs for this release: 20171002220106" == body["message"]
    assert body["link"] == ("https://buildhub.moz.tools/"
                            "?versions[0]=56.0.1rc2&products[0]=firefox&channel[0]=release")


async def test_candidates_buildhub_build(cli):
    resp = await check_response(cli, "/v1/firefox/56.0.1build2/buildhub")
    body = await resp.json()
    assert body["status"] == Status.EXISTS.value
    assert "Build IDs for this release: 20171002220106" == body["message"]
    assert body["link"] == ("https://buildhub.moz.tools/"
                            "?versions[0]=56.0.1rc2&products[0]=firefox&channel[0]=release")


async def test_devedition_buildhub(cli):
    resp = await check_response(cli, "/v1/devedition/58.0b15/buildhub")
    body = await resp.json()
    assert body["status"] == Status.EXISTS.value
    assert "Build IDs for this release: 20180108140638" == body["message"]
    assert body["link"] == ("https://buildhub.moz.tools/"
                            "?versions[0]=58.0b15&products[0]=devedition&channel[0]=aurora")


async def test_release_bedrock_release_notes(cli):
    await check_response(cli, "/v1/firefox/57.0.2/bedrock/release-notes", body={
        "status": Status.EXISTS.value,
        "message": "Release notes were found for version 57.0.2.",
        "link": "https://www.mozilla.org/en-US/firefox/57.0.2/releasenotes/"
    })


async def test_devedition_bedrock_release_notes(cli):
    await check_response(cli, "/v1/devedition/58.0b15/bedrock/release-notes", body={
        "status": Status.EXISTS.value,
        "message": "Release notes were found for version 58.0beta.",
        "link": "https://www.mozilla.org/en-US/firefox/58.0beta/releasenotes/"
    })


async def test_release_bedrock_esr_release_notes(cli):
    await check_response(cli, "/v1/firefox/52.5.2esr/bedrock/release-notes", body={
        "status": Status.EXISTS.value,
        "message": "Release notes were found for version 52.5.2.",
        "link": "https://www.mozilla.org/en-US/firefox/52.5.2/releasenotes/"
    })


async def test_release_bedrock_security_advisories(cli):
    resp = await check_response(cli, "/v1/firefox/54.0/bedrock/security-advisories")
    body = await resp.json()
    assert body['status'] == Status.EXISTS.value
    assert body['message'].startswith("Security advisories for release were updated up to version")
    assert body['link'] == "https://www.mozilla.org/en-US/security/known-vulnerabilities/firefox/"


async def test_release_bedrock_download_links(cli):
    resp = await check_response(cli, "/v1/firefox/54.0/bedrock/download-links")
    body = await resp.json()

    assert body['status'] == Status.EXISTS.value
    assert body['message'].startswith("The download links for release have been published")
    assert body['link'] == "https://www.mozilla.org/en-US/firefox/all/"


async def test_devedition_bedrock_download_links(cli):
    resp = await check_response(cli, "/v1/devedition/58.0b15/bedrock/download-links")
    body = await resp.json()

    assert body['status'] == Status.EXISTS.value
    assert body['message'].startswith("The download links for release have been published")
    url_prefix = "https://download-installer.cdn.mozilla.net/pub/devedition/releases/"
    assert body['link'].startswith(url_prefix)


# FIXME(willkg): This fails because mozilla.org redid their /firefox/all/ page.
# See https://github.com/mozilla/PollBot/issues/247
@pytest.mark.xfail
async def test_release_bouncer_download_links(cli):
    resp = await check_response(cli, "/v1/firefox/54.0/bouncer")
    body = await resp.json()

    assert body['status'] == Status.EXISTS.value
    assert body['message'].startswith("Bouncer for RELEASE redirects to version")
    url_prefix = "https://download-installer.cdn.mozilla.net/pub/firefox/releases/"
    assert body['link'].startswith(url_prefix)


async def test_devedition_bouncer_download_links(cli):
    resp = await check_response(cli, "/v1/devedition/58.0b15/bouncer")
    body = await resp.json()

    assert body['status'] == Status.EXISTS.value
    assert body['message'].startswith("Bouncer for DEVEDITION redirects to version")
    url_prefix = "https://download-installer.cdn.mozilla.net/pub/devedition/releases/"
    assert body['link'].startswith(url_prefix)


async def test_release_product_details(cli):
    await check_response(cli, "/v1/firefox/54.0/product-details", body={
        "status": Status.EXISTS.value,
        "message": "We found product-details information about version 54.0",
        "link": "https://product-details.mozilla.org/1.0/firefox.json"
    })


async def test_devedition_product_details(cli):
    await check_response(cli, "/v1/devedition/58.0b15/product-details", body={
        "status": Status.EXISTS.value,
        "message": "We found product-details information about version 58.0b15",
        "link": "https://product-details.mozilla.org/1.0/firefox.json"
    })


async def test_beta_product_details_devedition_and_beta_versions_matches(cli):
    await check_response(cli,
                         "/v1/firefox/56.0b7/product-details/devedition-beta-versions-matches",
                         status=200)


async def test_devedition_product_details_devedition_and_beta_versions_matches(cli):
    await check_response(cli,
                         "/v1/devedition/56.0b7/product-details/devedition-beta-versions-matches",
                         status=200)


async def test_release_product_details_devedition_and_beta_versions_matches(cli):
    url = "/v1/firefox/54.0/product-details/devedition-beta-versions-matches"
    await check_response(cli, url, body={
        "status": Status.MISSING.value,
        "message": "No devedition and beta check for 'release' releases",
        "link": "https://product-details.mozilla.org/1.0/firefox_versions.json"
    })


async def test_esr_balrog_rules(cli):
    resp = await check_response(cli, "/v1/firefox/52.3.0esr/balrog-rules")
    body = await resp.json()
    assert body["status"] == Status.EXISTS.value
    assert "Balrog rule has been updated" in body["message"]
    assert body["link"] == "https://aus-api.mozilla.org/api/v1/rules/esr52"


async def test_beta_balrog_rules(cli):
    resp = await check_response(cli, "/v1/firefox/56.0b7/balrog-rules")
    body = await resp.json()
    assert body["status"] in (Status.EXISTS.value, Status.INCOMPLETE.value)
    assert "Balrog rule has been updated" in body["message"]
    assert body["link"] == "https://aus-api.mozilla.org/api/v1/rules/firefox-beta"


async def test_devedition_balrog_rules(cli):
    resp = await check_response(cli, "/v1/devedition/56.0b7/balrog-rules")
    body = await resp.json()
    assert body["status"] in (Status.EXISTS.value, Status.INCOMPLETE.value)
    assert "Balrog rule has been updated for Devedition" in body["message"]
    assert body["link"] == "https://aus-api.mozilla.org/api/v1/rules/devedition"


async def test_nightly_balrog_rules(cli):
    resp = await check_response(cli, "/v1/firefox/57.0a1/balrog-rules")
    body = await resp.json()
    assert "Balrog rule is configured" in body["message"]
    assert body["status"] in (Status.EXISTS.value, Status.MISSING.value, Status.INCOMPLETE.value)
    assert body["link"] == "https://aus-api.mozilla.org/api/v1/rules/firefox-nightly"


async def test_firefox_releases_list(cli):
    resp = await check_response(cli, "/v1/firefox")
    body = await resp.json()
    assert "releases" in body
    assert all([isinstance(version, str) for version in body["releases"]])


async def test_devedition_releases_list(cli):
    resp = await check_response(cli, "/v1/devedition")
    body = await resp.json()
    assert "releases" in body
    assert all([isinstance(version, str) for version in body["releases"]])
    assert all(['rc' not in version for version in body["releases"]])


# Utilities
async def test_lbheartbeat(cli):
    await check_response(cli, "/v1/__lbheartbeat__",
                         body={
                             "status": "running"
                         })


async def test_heartbeat(cli):
    await check_response(cli, "/v1/__heartbeat__",
                         status=503,
                         body={
                             "archive": True,
                             "balrog": True,
                             "bedrock": True,
                             "bouncer": True,
                             "buildhub": True,
                             "product-details": True,
                             "telemetry": False,
                             "thunderbird_net": True,
                         })


async def test_version_view_return_404_if_missing_file(cli):
    with mock.patch("builtins.open", side_effect=IOError):
        await check_response(cli, "/v1/__version__",
                             status=404,
                             body={
                                 "status": 404,
                                 "message": "Page '/v1/__version__' not found"
                             })


async def test_version_view_return_200(cli):
    with open("version.json") as fd:
        await check_response(cli, "/v1/__version__",
                             body=json.load(fd))


async def test_ongoing_versions_response_validates_product_name(cli):
    await check_response(cli, "/v1/invalid-product/ongoing-versions", body={
        "status": 404,
        "message": "Invalid product: invalid-product not in ('firefox', "
                   "'devedition', 'thunderbird')"
    }, status=404)


async def test_ongoing_versions_view_firefox(cli):
    resp = await check_response(cli, "/v1/firefox/ongoing-versions")
    body = await resp.json()
    assert "esr" in body
    assert "release" in body
    assert "beta" in body
    assert "nightly" in body
    assert "devedition" not in body


async def test_ongoing_versions_view_devedition(cli):
    resp = await check_response(cli, "/v1/devedition/ongoing-versions")
    body = await resp.json()
    assert "devedition" in body


@pytest.mark.parametrize("endpoint", NO_CACHE_ENDPOINTS)
async def test_endpoint_have_got_cache_control_headers(cli, endpoint):
    resp = await cli.get(endpoint)
    assert "Cache-Control" in resp.headers
    assert resp.headers["Cache-Control"] == "no-cache"


async def test_product_endpoint_have_got_cache_control_headers(cli):
    resp = await cli.get("/v1/firefox/54.0")
    assert "Cache-Control" in resp.headers
    assert resp.headers["Cache-Control"] == "public; max-age=30"


async def test_cache_control_header_max_age_can_be_parametrized(cli):
    with mock.patch("pollbot.middlewares.CACHE_MAX_AGE", 10):
        resp = await cli.get("/v1/firefox/54.0")
        assert "Cache-Control" in resp.headers
        assert resp.headers["Cache-Control"] == "public; max-age=10"


async def test_get_buildid_for_version(cli):
    build_ids = await get_build_ids_for_version("firefox", "57.0b5")
    assert build_ids == ['20171002181526']


async def test_get_buildid_for_nightly_version(cli):
    build_ids = await get_build_ids_for_version("firefox", "57.0a1", size=100)
    assert build_ids == [
        '20170921100141', '20170920220431', '20170920100426', '20170919220202', '20170919100405',
        '20170918220054', '20170918100059', '20170917220255', '20170917100334', '20170916220246',
        '20170916100147', '20170915220136', '20170915100121', '20170914220209', '20170914100122',
        '20170913220121', '20170913100125', '20170912220343', '20170912100139', '20170912013600',
        '20170911100210', '20170910220126', '20170910100150', '20170909220406', '20170909100226',
        '20170908220146', '20170908100218', '20170907220212', '20170907100318', '20170906220306',
        '20170906100107', '20170905220108', '20170905100117', '20170904220027', '20170904100131',
        '20170903220032', '20170903100443', '20170902220453', '20170902100317', '20170901220209',
        '20170901151028', '20170901100309', '20170831220208', '20170831100258', '20170830220349',
        '20170830100230', '20170829100404', '20170828100127', '20170827100428', '20170826213134',
        '20170826100418', '20170825100126', '20170824100243', '20170823100553', '20170822142709',
        '20170822100529', '20170821100350', '20170820100343', '20170819100442', '20170818100226',
        '20170817100132', '20170816100153', '20170815213904', '20170815183542', '20170815100349',
        '20170814100258', '20170813183258', '20170813100233', '20170812100345', '20170811100330',
        '20170810100255', '20170809100326', '20170808114032', '20170808100224', '20170807113452',
        '20170807100344', '20170806100257', '20170805100334', '20170804193726', '20170804180022',
        '20170804100354', '20170803134456', '20170803100352', '20170802100302']
