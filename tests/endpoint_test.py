"""Opt-in HTTP checks against a developer-selected rendered form; no paid starts/submits."""
import argparse
import http.cookiejar
import json
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser

class Forms(HTMLParser):
    def __init__(self):
        super().__init__()
        self.forms = []
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'form' and 'data-gpt-live-token' in attrs:
            self.forms.append(attrs)

args = argparse.ArgumentParser(description=__doc__)
args.add_argument('url', help='Full URL of a development page containing the rendered form')
args.add_argument('--form', help='Form name; required only when the page contains multiple GPT-Live forms')
options = args.parse_args()
parts = urllib.parse.urlsplit(options.url)
if parts.scheme not in ('http', 'https') or not parts.netloc:
    args.error('Supply a full HTTP(S) page URL')
origin = urllib.parse.urlunsplit((parts.scheme, parts.netloc, '', '', ''))
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(url, payload=None, opener=client):
    headers = {}
    if payload is not None:
        headers.update({'Content-Type': 'application/json', 'Origin': origin})
    req = urllib.request.Request(url, None if payload is None else json.dumps(payload).encode(), headers)
    try:
        with opener.open(req) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()

status, html = request(options.url)
assert status == 200, status
parser = Forms()
parser.feed(html)
matches = [f for f in parser.forms if not options.form or f['data-gpt-live-form-name'] == options.form]
if len(matches) != 1:
    args.error('Expected one matching GPT-Live form; use --form to select it. For iframe embeds, supply the iframe URL.')
form = matches[0]
assert 1 <= int(form['data-gpt-live-request-timeout-seconds']) <= 300
print('PASS Action request timeout exported on rendered form')
messages = json.loads(form['data-gpt-live-messages'])
endpoint = urllib.parse.urljoin(options.url, form['data-gpt-live-session-url'])
# Never send the browser-bound token to an endpoint outside the selected site.
endpoint_parts = urllib.parse.urlsplit(endpoint)
assert (endpoint_parts.scheme, endpoint_parts.netloc) == (parts.scheme, parts.netloc)
token = form['data-gpt-live-token']
page = int(form['data-gpt-live-page-num'])
for name, payload, opener in [
    ('missing token', {'pageUpdate': True}, client),
    ('forged token', {'pageUpdate': True, 'token': '0' * 64}, client),
    ('different browser session', {'pageUpdate': True, 'token': token}, urllib.request.build_opener())
]:
    status, body = request(endpoint, payload, opener)
    assert status == 403, (name, status)
    assert json.loads(body)['error'] == messages['fallback'], name
    print('PASS ' + name + ' uses existing fallback')
for _ in range(7):
    status, body = request(endpoint, {'pageUpdate': True, 'pageNum': page, 'token': token, 'currentValues': {}})
    result = json.loads(body)
    assert status == 200 and 'responses' in result, (status, body[:100])
print('PASS repeated page refreshes accepted without consuming startup limits')
status, body = request(endpoint, {'pageUpdate': True, 'pageNum': int(form['data-gpt-live-page-count']) + 1, 'token': token})
assert status == 400 and json.loads(body)['error'] == messages['fallback']
print('PASS invalid request returns the same fallback')
