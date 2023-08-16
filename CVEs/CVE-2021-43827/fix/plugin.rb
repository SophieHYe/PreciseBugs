# frozen_string_literal: true
# transpile_js: true
# name: discourse-footnote
# about: Adds markdown.it footnote support to Discourse
# version: 0.2
# authors: Sam Saffron, Vitaly Puzrin
# url: https://github.com/discourse/discourse-footnote

enabled_site_setting :enable_markdown_footnotes

register_asset "vendor/javascripts/markdown-it-footnote.js", :vendored_pretty_text

register_asset "stylesheets/footnotes.scss"

register_svg_icon "ellipsis-h" if respond_to?(:register_svg_icon)

DiscourseEvent.on(:before_post_process_cooked) do |doc, post|
  doc.css('a.footnote-backref').each do |backref|
    href = backref["href"] || ""
    id = href[6..-1].to_i
    backref["href"] = "#footnote-ref-#{post.id}-#{id}"
  end

  doc.css('sup.footnote-ref a').each do |ref|
    href = ref["href"] || ""
    id = href[3..-1].to_i
    ref["href"] = "#footnote-#{post.id}-#{id}"

    id = ref["id"] || ""
    id = id[5..-1].to_i
    ref["id"] = "footnote-ref-#{post.id}-#{id}"
  end

  doc.css('li.footnote-item').each do |li|
    id = li["id"] || ""
    id = id[2..-1].to_i

    li["id"] = "footnote-#{post.id}-#{id}"
  end
end
