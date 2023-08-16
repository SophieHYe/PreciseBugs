import {
  acceptance,
  exists,
  query,
} from "discourse/tests/helpers/qunit-helpers";
import { visit } from "@ember/test-helpers";
import { test } from "qunit";
import topicFixtures from "discourse/tests/fixtures/topic";
import { cloneJSON } from "discourse-common/lib/object";

const COOKED_WITH_HEADINGS =
  '<h1>\n<a name="h1-first-test-edited-1" class="anchor" href="#h1-first-test-edited-1"></a>帖子控制</h1>\n<h2>\n<a name="measure-h2-2" class="anchor" href="#measure-h2-2"></a>Measure h2</h2>\n<p>Jaracaca Swamp we gazed round the very evening light in some. HTML version of science far too late. Wait a snake and nearly half-past two terrible carnivorous dinosaur and distribute. Employers Liability Act you! Each of me see that the crudest pleasantry. Sonny my own special brain. Advancing in front of them and there?</p>\n<div class="md-table">\n<table>\n<thead>\n<tr>\n<th>questions</th>\n<th>vanish</th>\n<th>contention</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td>nearer</td>\n<td>depressed</td>\n<td>francisca</td>\n</tr>\n<tr>\n<td>rooms</td>\n<td>kennel</td>\n<td>genesis</td>\n</tr>\n</tbody>\n</table>\n</div><h2>\n<a name="undeveloped-h2-3" class="anchor" href="#undeveloped-h2-3"></a>Undeveloped h2</h2>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.<br>\nCried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.<br>\nCried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<h1>\n<a name="h1-second-section-4" class="anchor" href="#h1-second-section-4"></a>H1 second section</h1>\n<h2>\n<a name="undeveloped-2-h2-5" class="anchor" href="#undeveloped-2-h2-5"></a>Undeveloped 2 h2</h2>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through.<br>\nYou’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<h3>\n<a name="subheading-3-h3-6" class="anchor" href="#subheading-3-h3-6"></a>Subheading 3 h3</h3>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<h3>\n<a name="subheading-3-long-ass-wire-h3-7" class="anchor" href="#subheading-3-long-ass-wire-h3-7"></a>Subheading 3 long ass wire h3</h3>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<h2>\n<a name="another-section-h2-8" class="anchor" href="#another-section-h2-8"></a>Another section h2</h2>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<h3>\n<a name="subheading-again-then-h3-9" class="anchor" href="#subheading-again-then-h3-9"></a>Subheading again then h3</h3>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<h4>\n<a name="su-subbheading-h4-10" class="anchor" href="#su-subbheading-h4-10"></a>Su-subbheading h4</h4>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<h4>\n<a name="su-subalicions-heading-h4-11" class="anchor" href="#su-subalicions-heading-h4-11"></a>Su-subalicions heading h4</h4>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<h4>\n<a name="su-subalicions-heading-h4-quite-long-to-test-a-real-life-kind-of-scenario-here-then-12" class="anchor" href="#su-subalicions-heading-h4-quite-long-to-test-a-real-life-kind-of-scenario-here-then-12"></a>Su-subalicions heading h4 quite long to test a real-life kind of scenario here then</h4>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<h4>\n<a name="su-subalicions-heading-h4-also-quite-long-to-test-a-real-life-kind-of-scenario-here-then-13" class="anchor" href="#su-subalicions-heading-h4-also-quite-long-to-test-a-real-life-kind-of-scenario-here-then-13"></a>Su-subalicions heading h4 also quite long to test a real-life kind of scenario here then</h4>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n<aside class="quote no-group" data-username="kathey.zemlak" data-post="2" data-topic="71">\n<div class="title">\n<div class="quote-controls"></div>\n<img alt="" width="20" height="20" src="//127.0.0.1:4200/user_avatar/127.0.0.1/kathey.zemlak/40/14_2.png" class="avatar"><a href="//127.0.0.1:4200/t/modernizing-the-antiquated-boxing-scoring-system/71/2">Modernizing the antiquated boxing scoring system</a>\n</div>\n<blockquote>\n<h2>Undeveloped</h2>\n<p>Cried leaning upon the tangle of the full of the same. Behind us upon the luxurious. Tarp Henry of the moment that similar upon his lecture. Devil got there came well with him fifteen dollars a whisper We slunk through. You’ll find its palm. Other ones and east of Shakespeare could his seat there by. McArdle looked round and I have thrown open and his people have seen the tangled.</p>\n</blockquote>\n</aside>';

const TOC_MARKUP = '\n<div data-theme-toc="true"></div>';
const TOC_AUTO_CATEGORIES = "17|19|13";
const TOC_AUTO_TAGS = "docs|knowledge";

const TOC_TOPIC_TAGS = ["design", "docs"];
const TOC_TOPIC_CATEGORY = 19;

acceptance("DiscoTOC - main", function (needs) {
  needs.pretender((server, helper) => {
    const topicResponse = cloneJSON(topicFixtures["/t/280/1.json"]);
    topicResponse.post_stream.posts[0].cooked =
      COOKED_WITH_HEADINGS + TOC_MARKUP;

    server.get("/t/280.json", () => helper.response(topicResponse));
    server.get("/t/280/:post_number.json", () =>
      helper.response(topicResponse)
    );
  });

  test("shows TOC, hides timeline on desktop", async function (assert) {
    await visit("/t/internationalization-localization/280");

    assert.ok(exists(".d-toc-wrapper #d-toc"), "TOC element exists");

    const firstH2 = query(".topic-body h2"),
      dTocID = firstH2.getAttribute("data-d-toc"),
      matchingTocItem = `#d-toc [data-d-toc="${dTocID}"]`;
    assert.equal(
      firstH2.textContent.trim(),
      query(matchingTocItem).textContent.trim(),
      "TOC above timeline has matching items"
    );

    const bquoteH2 = query(".topic-body blockquote h2");
    assert.ok(exists(bquoteH2), "blockquote H2 exists");
    assert.equal(
      bquoteH2.hasAttribute("data-d-toc"),
      false,
      "does not apply TOC to headings in blockquote"
    );

    assert.equal(
      firstH2.hasAttribute("data-d-toc"),
      true,
      "does apply TOC to regular headings"
    );

    const firstH1 = query(".topic-body h1");

    assert.equal(
      firstH1.getAttribute("id"),
      "toc-h1-0",
      "heading gets an ID even when it has no Latin characters"
    );
  });
});

acceptance("DiscoTOC - off", function (needs) {
  needs.pretender((server, helper) => {
    const topicResponse = cloneJSON(topicFixtures["/t/280/1.json"]);
    topicResponse.post_stream.posts[0].cooked = COOKED_WITH_HEADINGS;

    server.get("/t/280.json", () => helper.response(topicResponse));
    server.get("/t/280/:post_number.json", () =>
      helper.response(topicResponse)
    );
  });

  test("no TOC markup on a regular topic", async function (assert) {
    await visit("/t/internationalization-localization/280");
    assert.ok(!exists(".d-toc-wrapper #d-toc"));
  });
});

acceptance("DiscoTOC - with tags", function (needs) {
  needs.pretender((server, helper) => {
    settings.auto_TOC_tags = TOC_AUTO_TAGS;
    const topicResponse = cloneJSON(topicFixtures["/t/280/1.json"]);
    topicResponse.post_stream.posts[0].cooked = COOKED_WITH_HEADINGS;
    topicResponse.tags = TOC_TOPIC_TAGS;

    server.get("/t/280.json", () => helper.response(topicResponse));
    server.get("/t/280/:post_number.json", () =>
      helper.response(topicResponse)
    );
  });

  test("automaticly adds TOC based on tags", async function (assert) {
    await visit("/t/internationalization-localization/280");
    assert.ok(exists(".d-toc-wrapper #d-toc"));
  });
});

acceptance("DiscoTOC - with categories", function (needs) {
  needs.pretender((server, helper) => {
    settings.auto_TOC_categories = TOC_AUTO_CATEGORIES;
    const topicResponse = cloneJSON(topicFixtures["/t/280/1.json"]);
    topicResponse.post_stream.posts[0].cooked = COOKED_WITH_HEADINGS;
    topicResponse.category_id = TOC_TOPIC_CATEGORY;

    server.get("/t/280.json", () => helper.response(topicResponse));
    server.get("/t/280/:post_number.json", () =>
      helper.response(topicResponse)
    );
  });

  test("automaticly adds TOC based on category", async function (assert) {
    await visit("/t/internationalization-localization/280");
    assert.ok(exists(".d-toc-wrapper #d-toc"));
  });
});
