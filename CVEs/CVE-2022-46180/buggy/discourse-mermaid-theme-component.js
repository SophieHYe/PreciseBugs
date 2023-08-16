import loadScript from "discourse/lib/load-script";
import { apiInitializer } from "discourse/lib/api";
import discourseDebounce from "discourse-common/lib/debounce";

async function applyMermaid(element, key = "composer") {
  const mermaids = element.querySelectorAll("pre[data-code-wrap=mermaid]");

  if (!mermaids.length) {
    return;
  }

  await loadScript(settings.theme_uploads_local.mermaid_js);

  window.mermaid.initialize({
    startOnLoad: false,
    theme:
      getComputedStyle(document.body)
        .getPropertyValue("--scheme-type")
        .trim() === "dark"
        ? "dark"
        : "default",
  });

  mermaids.forEach((mermaid) => {
    if (mermaid.dataset.processed) {
      return;
    }

    const spinner = document.createElement("div");
    spinner.classList.add("spinner");

    if (mermaid.dataset.codeHeight && key !== "composer") {
      mermaid.style.height = `${mermaid.dataset.codeHeight}px`;
    }

    mermaid.append(spinner);
  });

  mermaids.forEach((mermaid, index) => {
    const code = mermaid.querySelector("code");

    if (!code) {
      return;
    }

    try {
      if (window.mermaid.parse(code.textContent || "")) {
        window.mermaid.mermaidAPI.render(
          `mermaid_${index}_${key}`,
          code.textContent || "",
          (svg) => {
            mermaid.innerHTML = svg;
          }
        );
      }
    } catch (e) {
      mermaid.innerHTML = `<span class="mermaid-error">${e?.str || e}</span>`;
    } finally {
      mermaid.dataset.processed = true;
      mermaid.querySelector(".spinner")?.remove();
    }

    if (key === "composer") {
      discourseDebounce(updateMarkdownHeight, mermaid, index, 500);
    }
  });
}

function updateMarkdownHeight(mermaid, index) {
  let height = parseInt(mermaid.getBoundingClientRect().height);
  let calculatedHeight = parseInt(mermaid.dataset.calculatedHeight);

  if (height === 0) {
    return;
  }

  if (height !== calculatedHeight) {
    mermaid.dataset.calculatedHeight = height;
    // TODO: an API to grab the composer vs leaning on hunting through HTML
    // would be better
    let composer = document.getElementsByClassName("d-editor-input")[0];

    let split = composer.value.split("\n");

    let n = 0;
    for (let i = 0; i < split.length; i++) {
      if (split[i].match(/```mermaid((\s*)|.*auto)$/)) {
        if (n === index) {
          split[i] = "```mermaid height=" + height + ",auto";
        }
        n += 1;
      }
    }

    let joined = split.join("\n");

    if (joined !== composer.value) {
      let restorePosStart = composer.selectionStart;
      let restorePosEnd = composer.selectionEnd;

      composer.value = joined;

      if (restorePosStart) {
        composer.selectionStart = restorePosStart;
        composer.selectionEnd = restorePosEnd;
      }
    }
  }
}

export default apiInitializer("0.11.1", (api) => {
  api.addToolbarPopupMenuOptionsCallback(() => {
    return {
      action: "insertMermaidSample",
      icon: "project-diagram",
      label: themePrefix("insert_mermaid_sample"),
    };
  });

  // this is a hack as applySurround expects a top level
  // composer key, not possible from a theme
  window.I18n.translations[
    window.I18n.locale
  ].js.composer.mermaid_sample = `    flowchart
         A --> B`;

  api.modifyClass("controller:composer", {
    pluginId: "discourse-mermaid-theme-component",
    actions: {
      insertMermaidSample() {
        this.toolbarEvent.applySurround(
          "\n```mermaid\n",
          "\n```\n",
          "mermaid_sample",
          { multiline: false }
        );
      },
    },
  });

  if (api.decorateChatMessage) {
    api.decorateChatMessage((element) => {
      applyMermaid(element, `chat_message_${element.id}`);
    });
  }

  api.decorateCookedElement(
    async (elem, helper) => {
      const id = helper ? `post_${helper.getModel().id}` : "composer";
      applyMermaid(elem, id);
    },
    { id: "discourse-mermaid-theme-component" }
  );
});
