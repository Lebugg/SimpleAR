---
title: "SimpleAR: Reference"
layout: article
---

# Reference

SimpleAR is divided in two main parts:

* Orm (namespace `SimpleAR\Orm`);
* Database (namespace `SimpleAR\Database`).

Here is an overview of the code organization:
![Code overview]({{ site.baseurl }}/assets/images/reference_overview.png
"Overview of code organization")

The first part describes the Query build process, the second deals with Model
and Relations. The third part explain how the two main parts (`Orm` and
`Database`) work together.


## Query build process

This section describes the process to build SQL queries.

### Overview

Query build process is made in three steps:

* Build;
* Compilation;
* Execution.

The conductor of this process is the Query object. Give it a Connection and a
Builder instances, and you have your entry point!

### Builder

The builder is aimed to provide a rich interface for the user to construct the
query: `where()`, `whereHas()`, `limit()`...

There is one builder per query type.

### Compiler

The compiler build the query components.

### Connection


## Models and relations

### The `Model`
### Relations


## Orm query builder
