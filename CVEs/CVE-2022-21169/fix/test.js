/* eslint-disable prettier/prettier */
/* eslint-disable func-names */
/* eslint-disable no-undef */

"use strict";

const request = require("supertest");
const express = require("express");
const bodyParser = require("body-parser");
const { expect } = require("chai");
const { xss, sanitize } = require("../index");

describe("Express xss Sanitize", function () {
  describe("Sanitize with default settings as middleware before all routes", function () {
    const app = express();
    app.use(bodyParser.urlencoded({ extended: true }));
    app.use(bodyParser.json());
    app.use(xss());

    app.post("/body", function (req, res) {
      res.status(200).json({
        body: req.body,
      });
    });

    app.post("/headers", function (req, res) {
      res.status(200).json({
        headers: req.headers,
      });
    });

    app.get("/query", function (req, res) {
      res.status(200).json({
        query: req.query,
      });
    });
    describe("Sanitize simple object", function () {
      it("should sanitize clean body.", function (done) {
        request(app)
          .post("/body")
          .send({
            y: 4,
            z: false,
            w: "bla bla",
            a: "<p>Test</p>",
          })
          .expect(
            200,
            {
              body: {
                y: 4,
                z: false,
                w: "bla bla",
                a: "<p>Test</p>",
              },
            },
            done,
          );
      });

      it("should sanitize clean headers.", function (done) {
        request(app)
          .post("/headers")
          .set({
            y: "4",
            z: "false",
            w: "bla bla",
            a: "<p>Test</p>",
          })
          .expect(200)
          .expect(function (res) {
            expect(res.body.headers).to.include({
              y: "4",
              z: "false",
              w: "bla bla",
              a: "<p>Test</p>",
            });
          })
          .end(done);
      });

      it("should sanitize clean query.", function (done) {
        request(app)
          .get("/query?y=4&z=false&w=bla bla&a=<p>Test</p>")
          .expect(
            200,
            {
              query: {
                y: "4",
                z: "false",
                w: "bla bla",
                a: "<p>Test</p>",
              },
            },
            done,
          );
      });

      it("should sanitize dirty body.", function (done) {
        request(app)
          .post("/body")
          .send({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
          })
          .expect(
            200,
            {
              body: {
                a: "",
                b: "<p>Test</p>",
                c: "",
              },
            },
            done,
          );
      });

      it("should sanitize dirty query.", function (done) {
        request(app)
          .get(
            '/query?a=<script>Test</script>&b=<p onclick="return;">Test</p>&c=<img src="/"/>',
          )
          .expect(
            200,
            {
              query: {
                a: "",
                b: "<p>Test</p>",
                c: "",
              },
            },
            done,
          );
      });

      it("should sanitize dirty headers.", function (done) {
        request(app)
          .post("/headers")
          .set({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
          })
          .expect(200)
          .expect(function (res) {
            expect(res.body.headers).to.include({
              a: "",
              b: "<p>Test</p>",
              c: "",
            });
          })
          .end(done);
      });
    });

    describe("Sanitize complex object", function () {
      it("should sanitize clean body.", function (done) {
        request(app)
          .post("/body")
          .send({
            y: 4,
            z: false,
            w: "bla bla",
            a: "<p>Test</p>",
            arr: [
              "<h1>H1 Test</h1>",
              "bla bla",
              {
                i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                j: '<a href="/">Link</a>',
              },
            ],
            obj: {
              e: "Test1",
              r: {
                a: "<h6>H6 Test</h6>",
              },
            },
          })
          .expect(
            200,
            {
              body: {
                y: 4,
                z: false,
                w: "bla bla",
                a: "<p>Test</p>",
                arr: [
                  "<h1>H1 Test</h1>",
                  "bla bla",
                  {
                    i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                    j: '<a href="/">Link</a>',
                  },
                ],
                obj: {
                  e: "Test1",
                  r: {
                    a: "<h6>H6 Test</h6>",
                  },
                },
              },
            },
            done,
          );
      });

      it("should sanitize dirty body.", function (done) {
        request(app)
          .post("/body")
          .send({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
            arr: [
              "<h1 onclick='return false;'>H1 Test</h1>",
              "bla bla",
              {
                i: [
                  "<h3 onclick='function x(e) {console.log(e); return;}'>H3 Test</h3>",
                  "bla bla",
                  false,
                  5,
                ],
                j: '<a href="/" onclick="return 0;">Link</a>',
              },
            ],
            obj: {
              e: '<script>while (true){alert("Test To OO")}</script>',
              r: {
                a: "<h6>H6 Test</h6>",
              },
            },
          })
          .expect(
            200,
            {
              body: {
                a: "",
                b: "<p>Test</p>",
                c: "",
                arr: [
                  "<h1>H1 Test</h1>",
                  "bla bla",
                  {
                    i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                    j: '<a href="/">Link</a>',
                  },
                ],
                obj: {
                  e: "",
                  r: {
                    a: "<h6>H6 Test</h6>",
                  },
                },
              },
            },
            done,
          );
      });
    });
  });

  describe("Sanitize with custom options as middleware before all routes", function () {
    const app = express();
    const options = {
      allowedKeys: ["c"],
    };
    app.use(bodyParser.urlencoded({ extended: true }));
    app.use(bodyParser.json());
    app.use(xss(options));

    app.post("/body", function (req, res) {
      res.status(200).json({
        body: req.body,
      });
    });

    app.post("/headers", function (req, res) {
      res.status(200).json({
        headers: req.headers,
      });
    });

    app.get("/query", function (req, res) {
      res.status(200).json({
        query: req.query,
      });
    });
    describe("Sanitize simple object", function () {
      it("should sanitize clean body.", function (done) {
        request(app)
          .post("/body")
          .send({
            y: 4,
            z: false,
            w: "bla bla",
            a: "<p>Test</p>",
          })
          .expect(
            200,
            {
              body: {
                y: 4,
                z: false,
                w: "bla bla",
                a: "<p>Test</p>",
              },
            },
            done,
          );
      });

      it("should sanitize clean headers.", function (done) {
        request(app)
          .post("/headers")
          .set({
            y: "4",
            z: "false",
            w: "bla bla",
            a: "<p>Test</p>",
          })
          .expect(200)
          .expect(function (res) {
            expect(res.body.headers).to.include({
              y: "4",
              z: "false",
              w: "bla bla",
              a: "<p>Test</p>",
            });
          })
          .end(done);
      });

      it("should sanitize clean query.", function (done) {
        request(app)
          .get("/query?y=4&z=false&w=bla bla&a=<p>Test</p>")
          .expect(
            200,
            {
              query: {
                y: "4",
                z: "false",
                w: "bla bla",
                a: "<p>Test</p>",
              },
            },
            done,
          );
      });

      it("should sanitize dirty body.", function (done) {
        request(app)
          .post("/body")
          .send({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
          })
          .expect(
            200,
            {
              body: {
                a: "",
                b: "<p>Test</p>",
                c: '<img src="/"/>',
              },
            },
            done,
          );
      });

      it("should sanitize dirty query.", function (done) {
        request(app)
          .get(
            '/query?a=<script>Test</script>&b=<p onclick="return;">Test</p>&c=<img src="/"/>',
          )
          .expect(
            200,
            {
              query: {
                a: "",
                b: "<p>Test</p>",
                c: '<img src="/"/>',
              },
            },
            done,
          );
      });

      it("should sanitize dirty headers.", function (done) {
        request(app)
          .post("/headers")
          .set({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
          })
          .expect(200)
          .expect(function (res) {
            expect(res.body.headers).to.include({
              a: "",
              b: "<p>Test</p>",
              c: '<img src="/"/>',
            });
          })
          .end(done);
      });
    });

    describe("Sanitize complex object", function () {
      it("should sanitize clean body.", function (done) {
        request(app)
          .post("/body")
          .send({
            y: 4,
            z: false,
            w: "bla bla",
            a: "<p>Test</p>",
            arr: [
              "<h1>H1 Test</h1>",
              "bla bla",
              {
                i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                j: '<a href="/">Link</a>',
              },
            ],
            obj: {
              e: "Test1",
              r: {
                a: "<h6>H6 Test</h6>",
              },
            },
          })
          .expect(
            200,
            {
              body: {
                y: 4,
                z: false,
                w: "bla bla",
                a: "<p>Test</p>",
                arr: [
                  "<h1>H1 Test</h1>",
                  "bla bla",
                  {
                    i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                    j: '<a href="/">Link</a>',
                  },
                ],
                obj: {
                  e: "Test1",
                  r: {
                    a: "<h6>H6 Test</h6>",
                  },
                },
              },
            },
            done,
          );
      });

      it("should sanitize dirty body.", function (done) {
        request(app)
          .post("/body")
          .send({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
            arr: [
              "<h1 onclick='return false;'>H1 Test</h1>",
              "bla bla",
              {
                i: [
                  "<h3 onclick='function x(e) {console.log(e); return;}'>H3 Test</h3>",
                  "bla bla",
                  false,
                  5,
                ],
                j: '<a href="/" onclick="return 0;">Link</a>',
              },
            ],
            obj: {
              e: '<script>while (true){alert("Test To OO")}</script>',
              r: {
                a: "<h6>H6 Test</h6>",
              },
            },
          })
          .expect(
            200,
            {
              body: {
                a: "",
                b: "<p>Test</p>",
                c: '<img src="/"/>',
                arr: [
                  "<h1>H1 Test</h1>",
                  "bla bla",
                  {
                    i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                    j: '<a href="/">Link</a>',
                  },
                ],
                obj: {
                  e: "",
                  r: {
                    a: "<h6>H6 Test</h6>",
                  },
                },
              },
            },
            done,
          );
      });
    });
  });

  describe("Sanitize with default settings as middleware before each route", function () {
    const app = express();
    app.use(bodyParser.urlencoded({ extended: true }));
    app.use(bodyParser.json());

    app.post("/body", xss(), function (req, res) {
      res.status(200).json({
        body: req.body,
      });
    });

    app.post("/headers", xss(), function (req, res) {
      res.status(200).json({
        headers: req.headers,
      });
    });

    app.get("/query", function (req, res) {
      res.status(200).json({
        query: req.query,
      });
    });
    describe("Sanitize simple object", function () {
      it("should sanitize clean body.", function (done) {
        request(app)
          .post("/body")
          .send({
            y: 4,
            z: false,
            w: "bla bla",
            a: "<p>Test</p>",
          })
          .expect(
            200,
            {
              body: {
                y: 4,
                z: false,
                w: "bla bla",
                a: "<p>Test</p>",
              },
            },
            done,
          );
      });

      it("should sanitize clean headers.", function (done) {
        request(app)
          .post("/headers")
          .set({
            y: "4",
            z: "false",
            w: "bla bla",
            a: "<p>Test</p>",
          })
          .expect(200)
          .expect(function (res) {
            expect(res.body.headers).to.include({
              y: "4",
              z: "false",
              w: "bla bla",
              a: "<p>Test</p>",
            });
          })
          .end(done);
      });

      it("should sanitize clean query.", function (done) {
        request(app)
          .get("/query?y=4&z=false&w=bla bla&a=<p>Test</p>")
          .expect(
            200,
            {
              query: {
                y: "4",
                z: "false",
                w: "bla bla",
                a: "<p>Test</p>",
              },
            },
            done,
          );
      });

      it("should sanitize dirty body.", function (done) {
        request(app)
          .post("/body")
          .send({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
          })
          .expect(
            200,
            {
              body: {
                a: "",
                b: "<p>Test</p>",
                c: "",
              },
            },
            done,
          );
      });

      it("should not sanitize dirty query.", function (done) {
        request(app)
          .get(
            '/query?a=<script>Test</script>&b=<p onclick="return;">Test</p>&c=<img src="/"/>',
          )
          .expect(
            200,
            {
              query: {
                a: "<script>Test</script>",
                b: '<p onclick="return;">Test</p>',
                c: '<img src="/"/>',
              },
            },
            done,
          );
      });

      it("should sanitize dirty headers.", function (done) {
        request(app)
          .post("/headers")
          .set({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
          })
          .expect(200)
          .expect(function (res) {
            expect(res.body.headers).to.include({
              a: "",
              b: "<p>Test</p>",
              c: "",
            });
          })
          .end(done);
      });
    });

    describe("Sanitize complex object", function () {
      it("should sanitize clean body.", function (done) {
        request(app)
          .post("/body")
          .send({
            y: 4,
            z: false,
            w: "bla bla",
            a: "<p>Test</p>",
            arr: [
              "<h1>H1 Test</h1>",
              "bla bla",
              {
                i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                j: '<a href="/">Link</a>',
              },
            ],
            obj: {
              e: "Test1",
              r: {
                a: "<h6>H6 Test</h6>",
              },
            },
          })
          .expect(
            200,
            {
              body: {
                y: 4,
                z: false,
                w: "bla bla",
                a: "<p>Test</p>",
                arr: [
                  "<h1>H1 Test</h1>",
                  "bla bla",
                  {
                    i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                    j: '<a href="/">Link</a>',
                  },
                ],
                obj: {
                  e: "Test1",
                  r: {
                    a: "<h6>H6 Test</h6>",
                  },
                },
              },
            },
            done,
          );
      });

      it("should sanitize dirty body.", function (done) {
        request(app)
          .post("/body")
          .send({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
            arr: [
              "<h1 onclick='return false;'>H1 Test</h1>",
              "bla bla",
              {
                i: [
                  "<h3 onclick='function x(e) {console.log(e); return;}'>H3 Test</h3>",
                  "bla bla",
                  false,
                  5,
                ],
                j: '<a href="/" onclick="return 0;">Link</a>',
              },
            ],
            obj: {
              e: '<script>while (true){alert("Test To OO")}</script>',
              r: {
                a: "<h6>H6 Test</h6>",
              },
            },
          })
          .expect(
            200,
            {
              body: {
                a: "",
                b: "<p>Test</p>",
                c: "",
                arr: [
                  "<h1>H1 Test</h1>",
                  "bla bla",
                  {
                    i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                    j: '<a href="/">Link</a>',
                  },
                ],
                obj: {
                  e: "",
                  r: {
                    a: "<h6>H6 Test</h6>",
                  },
                },
              },
            },
            done,
          );
      });
    });
  });

  describe("Sanitize with custom options as middleware before each route", function () {
    const app = express();
    app.use(bodyParser.urlencoded({ extended: true }));
    app.use(bodyParser.json());

    app.post("/body", xss({ allowedKeys: ["c"] }), function (req, res) {
      res.status(200).json({
        body: req.body,
      });
    });

    app.post("/headers", xss(), function (req, res) {
      res.status(200).json({
        headers: req.headers,
      });
    });

    app.get("/query", function (req, res) {
      res.status(200).json({
        query: req.query,
      });
    });
    describe("Sanitize simple object", function () {
      it("should sanitize clean body.", function (done) {
        request(app)
          .post("/body")
          .send({
            y: 4,
            z: false,
            w: "bla bla",
            a: "<p>Test</p>",
          })
          .expect(
            200,
            {
              body: {
                y: 4,
                z: false,
                w: "bla bla",
                a: "<p>Test</p>",
              },
            },
            done,
          );
      });

      it("should sanitize clean headers.", function (done) {
        request(app)
          .post("/headers")
          .set({
            y: "4",
            z: "false",
            w: "bla bla",
            a: "<p>Test</p>",
          })
          .expect(200)
          .expect(function (res) {
            expect(res.body.headers).to.include({
              y: "4",
              z: "false",
              w: "bla bla",
              a: "<p>Test</p>",
            });
          })
          .end(done);
      });

      it("should sanitize clean query.", function (done) {
        request(app)
          .get("/query?y=4&z=false&w=bla bla&a=<p>Test</p>")
          .expect(
            200,
            {
              query: {
                y: "4",
                z: "false",
                w: "bla bla",
                a: "<p>Test</p>",
              },
            },
            done,
          );
      });

      it("should sanitize dirty body.", function (done) {
        request(app)
          .post("/body")
          .send({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
          })
          .expect(
            200,
            {
              body: {
                a: "",
                b: "<p>Test</p>",
                c: '<img src="/"/>',
              },
            },
            done,
          );
      });

      it("should not sanitize dirty query.", function (done) {
        request(app)
          .get(
            '/query?a=<script>Test</script>&b=<p onclick="return;">Test</p>&c=<img src="/"/>',
          )
          .expect(
            200,
            {
              query: {
                a: "<script>Test</script>",
                b: '<p onclick="return;">Test</p>',
                c: '<img src="/"/>',
              },
            },
            done,
          );
      });

      it("should sanitize dirty headers.", function (done) {
        request(app)
          .post("/headers")
          .set({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
          })
          .expect(200)
          .expect(function (res) {
            expect(res.body.headers).to.include({
              a: "",
              b: "<p>Test</p>",
              c: "",
            });
          })
          .end(done);
      });
    });

    describe("Sanitize complex object", function () {
      it("should sanitize clean body.", function (done) {
        request(app)
          .post("/body")
          .send({
            y: 4,
            z: false,
            w: "bla bla",
            a: "<p>Test</p>",
            arr: [
              "<h1>H1 Test</h1>",
              "bla bla",
              {
                i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                j: '<a href="/">Link</a>',
                c: '<img src="/"/>',
              },
            ],
            obj: {
              e: "Test1",
              r: {
                a: "<h6>H6 Test</h6>",
              },
            },
          })
          .expect(
            200,
            {
              body: {
                y: 4,
                z: false,
                w: "bla bla",
                a: "<p>Test</p>",
                arr: [
                  "<h1>H1 Test</h1>",
                  "bla bla",
                  {
                    i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                    j: '<a href="/">Link</a>',
                    c: '<img src="/"/>',
                  },
                ],
                obj: {
                  e: "Test1",
                  r: {
                    a: "<h6>H6 Test</h6>",
                  },
                },
              },
            },
            done,
          );
      });

      it("should sanitize dirty body.", function (done) {
        request(app)
          .post("/body")
          .send({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
            arr: [
              "<h1 onclick='return false;'>H1 Test</h1>",
              "bla bla",
              {
                i: [
                  "<h3 onclick='function x(e) {console.log(e); return;}'>H3 Test</h3>",
                  "bla bla",
                  false,
                  5,
                ],
                j: '<a href="/" onclick="return 0;">Link</a>',
              },
            ],
            obj: {
              e: '<script>while (true){alert("Test To OO")}</script>',
              r: {
                a: "<h6>H6 Test</h6>",
              },
            },
          })
          .expect(
            200,
            {
              body: {
                a: "",
                b: "<p>Test</p>",
                c: '<img src="/"/>',
                arr: [
                  "<h1>H1 Test</h1>",
                  "bla bla",
                  {
                    i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                    j: '<a href="/">Link</a>',
                  },
                ],
                obj: {
                  e: "",
                  r: {
                    a: "<h6>H6 Test</h6>",
                  },
                },
              },
            },
            done,
          );
      });
    });
  });

  describe("Sanitize data with default settings as function", function () {
    describe("Sanitize simple object", function () {
      it("should sanitize clean body.", function (done) {
        expect(sanitize({
          y: 4,
          z: false,
          w: "bla bla",
          a: "<p>Test</p>",
        })).to.eql({
          y: 4,
          z: false,
          w: "bla bla",
          a: "<p>Test</p>",
        });
        done();
      });

      it("should sanitize dirty body.", function (done) {
        expect(sanitize({
          a: "<script>Test</script>",
          b: '<p onclick="return;">Test</p>',
          c: '<img src="/"/>',
        })).to.eql({
          a: "",
          b: "<p>Test</p>",
          c: "",
        });
        done();
      });
    });

    describe("Sanitize complex object", function () {
      it("should sanitize clean body.", function (done) {
        expect(
          sanitize({
            y: 4,
            z: false,
            w: "bla bla",
            a: "<p>Test</p>",
            arr: [
              "<h1>H1 Test</h1>",
              "bla bla",
              {
                i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
                j: '<a href="/">Link</a>',
              },
            ],
            obj: {
              e: "Test1",
              r: {
                a: "<h6>H6 Test</h6>",
              },
            },
          }),
        ).to.eql({
          y: 4,
          z: false,
          w: "bla bla",
          a: "<p>Test</p>",
          arr: [
            "<h1>H1 Test</h1>",
            "bla bla",
            {
              i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
              j: '<a href="/">Link</a>',
            },
          ],
          obj: {
            e: "Test1",
            r: {
              a: "<h6>H6 Test</h6>",
            },
          },
        });
        done();
      });

      it("should sanitize dirty body.", function (done) {
        expect(
          sanitize({
            a: "<script>Test</script>",
            b: '<p onclick="return;">Test</p>',
            c: '<img src="/"/>',
            arr: [
              "<h1 onclick='return false;'>H1 Test</h1>",
              "bla bla",
              {
                i: [
                  "<h3 onclick='function x(e) {console.log(e); return;}'>H3 Test</h3>",
                  "bla bla",
                  false,
                  5,
                ],
                j: '<a href="/" onclick="return 0;">Link</a>',
              },
            ],
            obj: {
              e: '<script>while (true){alert("Test To OO")}</script>',
              r: {
                a: "<h6>H6 Test</h6>",
              },
            },
          }),
        ).to.eql({
          a: "",
          b: "<p>Test</p>",
          c: "",
          arr: [
            "<h1>H1 Test</h1>",
            "bla bla",
            {
              i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
              j: '<a href="/">Link</a>',
            },
          ],
          obj: {
            e: "",
            r: {
              a: "<h6>H6 Test</h6>",
            },
          },
        });
        done();
      });
    });

    describe("Sanitize null value", function () {
      it("should return null.", function (done) {
        expect(
          sanitize(null),
        ).to.eql(null);
        done();
      });
    });
  });

  describe("Sanitize data with custom options as function", function () {
    describe("Sanitize simple object", function () {
      it("should sanitize dirty body.", function (done) {
        expect(sanitize({
          a: "<script>Test</script>",
          b: '<p onclick="return;">Test</p>',
          c: '<img src="/"/>',
        }, { allowedKeys: ["c"] })).to.eql({
          a: "",
          b: "<p>Test</p>",
          c: '<img src="/"/>',
        });
        done();
      });
    });

    describe("Sanitize complex object", function () {
      it("should sanitize dirty body.", function (done) {
        expect(sanitize({
          a: "<script>Test</script>",
          b: '<p onclick="return;">Test</p>',
          c: '<img src="/"/>',
          arr: [
            "<h1 onclick='return false;'>H1 Test</h1>",
            "bla bla",
            {
              i: [
                "<h3 onclick='function x(e) {console.log(e); return;}'>H3 Test</h3>",
                "bla bla",
                false,
                5,
              ],
              j: '<a href="/" onclick="return 0;">Link</a>',
            },
          ],
          obj: {
            e: '<script>while (true){alert("Test To OO")}</script>',
            r: {
              a: "<h6>H6 Test</h6>",
            },
          },
        }, { allowedKeys: ["e"] })).to.eql({
          a: "",
          b: "<p>Test</p>",
          c: "",
          arr: [
            "<h1>H1 Test</h1>",
            "bla bla",
            {
              i: ["<h3>H3 Test</h3>", "bla bla", false, 5],
              j: '<a href="/">Link</a>',
            },
          ],
          obj: {
            e: '<script>while (true){alert("Test To OO")}</script>',
            r: {
              a: "<h6>H6 Test</h6>",
            },
          },
        });
        done();
      });
    });
  });

  describe("Sanitize data with custom options as function", function () {
    describe("Sanitize simple object", function () {
      it("should sanitize dirty body.", function (done) {
        expect(sanitize({
          a: "<script>Test</script>",
          b: '<p onclick="return;">Test</p>',
          c: '<img src="/"/>',
        }, { allowedKeys: ["c"] })).to.eql({
          a: "",
          b: "<p>Test</p>",
          c: '<img src="/"/>',
        });
        done();
      });
    });

    describe("XSS bypass by using prototype pollution issue", function () {
      it("should sanitize dirty data after prototype pollution.", function (done) {
        // eslint-disable-next-line no-extend-native
        Object.prototype.allowedTags = ['script'];
        expect(sanitize({
          a: "<script>Test</script>",
        }, {})).to.eql({
          a: "",
        });
        done();
      });
    });
  });
});
