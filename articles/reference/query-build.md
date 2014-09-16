---
title: "SimpleAR: Reference - Query build process"
layout: article
---

# Query build process

This section describes the process to build SQL queries.

## Overview

Query build process is made in three steps:

* Build;
* Compilation;
* Execution.

The conductor of this process is the Query object. Give it a Connection and a
Builder instances, and you have your entry point!

## Builder

The builder is aimed to provide a rich interface for the user to construct the
query: `where()`, `whereHas()`, `limit()`...

There is one builder per query type.

## Compiler

The compiler build the query components.

Currently, there is one `BaseCompiler` class to handle any query. This basic
compiler can be extended to adapt to different DBMS.

Appropriate Compiler is chosen by Connection.

## Connection

This is the bridge between the database and the rest of the code.

