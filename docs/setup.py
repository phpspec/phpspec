#!/usr/bin/env python
#
# -*- coding: utf-8 -*-
import sys
import os
on_rtd = os.environ.get('READTHEDOCS', None) == 'True'
if on_rtd:
    html_theme = 'default'
else:
    html_theme = 'nature'
