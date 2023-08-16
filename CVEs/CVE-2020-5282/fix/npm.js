require('dotenv').config()
const { exec } = require("child_process");
const { RichEmbed, Message } = require("discord.js");
const { escape } = require("querystring")
const fetch = require("node-fetch")
module.exports = {
  name: "npm",
  args: true,
  usage: "<query>",
  aliases: ["pnpm"],
  description: "search a package on npm",
/**
  * @param { Message } message 
  * @param { Array<string> } args
 */
  execute: async (message, args) => {
    message.channel.startTyping();
    message.channel.send(
      `Searching \`${args
        .join(" ")
        .replace(/\n/g, " ")}\` on ${message.client.emojis.get(
         process.env.NPM_EMOJI_ID
        )}...`,
      { disableEveryone: true }
    );
    const response = await fetch("https://www.npmjs.com/search/suggestions?q=" + escape(args.join(" "))).then(r => r.json())
       const res = response.map(
            (x, index) => `${index + 1}. ${x.name}`
          );
          message.channel
            .send(
              "Type the number to see the details (10 seconds)\n" +
              res.join("\n"),
              { code: "xl", split: true }
            )
            .then(m => {
              message.channel
                .createMessageCollector(
                  x => x.author.id === message.author.id && parseInt(x.content),
                  { maxMatches: 1, time: 10000 }
                )
                .on("collect", msg => {
                  const choice = parseInt(msg.content) - 1;
                  if (!response[choice])
                    return message.reply("out of range.");
                  const result = response[choice];
                  const embed = new RichEmbed()
                    .setColor("#ff0000")
                    .setTitle(result.name)
                  if (result.links)
                    embed.setURL(result.links.npm)
                  embed
                    .setAuthor(
                      "npm",
                      "https://static.npmjs.com/338e4905a2684ca96e08c7780fc68412.png"
                    )
                    .addField("Last update", result.date)
                    .addField("Version", result.version)
                    .addField("Scope", result.scope)
                    .setDescription(
                      result.description + `\`\`\`npm i ${result.name}\`\`\``
                    );
                  if (result.keywords)
                    embed.addField("Keywords", result.keywords.join(","));
                  if (result.author)
                    embed.addField("Author", `${result.author.name}`);
                  if (result.maintainers)
                    embed.addField(
                      "Maintainers",
                      result.maintainers
                        .map(x => x.name || x.username)
                        .join(",")
                    );
                  if (result.contributors) embed.addField(
                    "Contributors",
                    result.contributors
                      .map(x => x.name || x.username)
                      .join(",")
                  );
                  const data = [];
                  for (const key of Object.keys(result.links || {})) {
                    data.push(`${key}: ${result.links[key]}`);
                  }
                  embed.addField("Links", data.join("\n") || "N/A");
                  message.channel.send(embed);
                });
            });
            message.channel.stopTyping();
        }
  }
