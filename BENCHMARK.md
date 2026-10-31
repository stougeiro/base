# Benchmark

Reference benchmark for the HTTP overhead of `stougeiro/base`.

This measures the Base request lifecycle behind nginx and PHP-FPM, not a
complete application. Database queries, external services, serialization,
uploads, and business rules are outside this measurement.

## Environment

| Item | Value |
| --- | --- |
| Base revision | `cab475a` |
| Host | Docker Desktop on WSL2 |
| CPU | 4 vCPU |
| Memory | 5.8 GiB |
| PHP | 8.2.35 FPM Alpine |
| PHP-FPM | static pool, 80 workers |
| nginx | 1.31.8 Alpine |
| OPcache | enabled, `validate_timestamps=0`, JIT off |
| Load generator | k6 v2.3.0 in Docker |

## Endpoint

The benchmark used a no-op request handler bound through the normal Base
kernel lifecycle:

```text
request -> nginx -> PHP-FPM -> Kernel::create() -> Kernel::handle() -> 200 "ok"
```

The endpoint did not use routing, module middleware, database, cache, or
external calls. It isolates the Base kernel and HTTP stack overhead.

## Workload

Warmup:

- 10 VUs for 15 seconds.
- Result: approximately 2,027 req/s, p95 10.41 ms.

Measurement:

- `constant-arrival-rate`
- 500 iterations per second
- 60 seconds
- 200 preallocated VUs, 500 maximum VUs

Thresholds:

- 0 failed requests
- 0 dropped iterations
- at least 30,000 requests
- p95 <= 100 ms
- p99 <= 200 ms

These thresholds apply to the 500 req/s baseline and to each saturation step.
The 30,000-request minimum applies only to the baseline. For the baseline, a run
with dropped iterations is discarded and retried with more VUs; for the
saturation probe, dropped iterations are a failed step and are reported as the
result.

### Saturation probe setup

After the baseline run, the same no-op endpoint was stepped up with
`constant-arrival-rate`, 60 seconds per step. The steps were 1,250, 1,500,
1,750, 2,000, 2,500, and 3,000 req/s.

- 1,200 preallocated VUs for steps at or above 1,250 req/s (`vus_max` was
  1,200 through 2,500 req/s).
- When a step dropped iterations, it was retried once with doubled VUs (2,400
  preallocated VUs in the 3,000 req/s retry).
- The sweep stopped at the first step that still failed after the retry.
- The probe had no per-step warmup or cooldown; the harness ran a single warmup
  before its 500 req/s baseline run.

## Baseline results (500 req/s)

| Run | RPS | p50 | p95 | p99 | Max | Failed | Dropped |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 1 | 499.98 | 1.52 ms | 3.17 ms | 8.47 ms | 73.37 ms | 0 | 0 |
| 2 | 499.95 | 2.60 ms | 13.71 ms | 40.11 ms | 327.51 ms | 0 | 0 |

Both runs completed 30,000 requests in 60 seconds.

Resource usage during the measured runs:

- PHP-FPM peak: 50.72% to 82.11% of one CPU core.
- nginx peak: 37.73% to 54.76% of one CPU core.
- Total peak: approximately 1.37 of the 4 available cores.
- Memory stayed stable at approximately 74 MiB for PHP-FPM and 7 MiB for
  nginx.

The first attempt used 100 preallocated VUs and dropped 143 iterations.
Increasing the preallocation to 200 removed the drops; the limit was the load
generator, not the Base layer.

## Saturation probe (clean-state rate sweep)

In a separate sweep following the baseline methodology, the same no-op endpoint
was stepped up with `constant-arrival-rate`, 60 seconds per step. The sweep was
run in the best host state observed, with no concurrent benchmark load. In this
sweep, the endpoint sustained 2,500 req/s with zero failed requests and zero
dropped iterations:

| Target | RPS | p50 | p95 | p99 | Max | Failed | Dropped |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 1,250 | 1,249.93 | 1.15 ms | 2.29 ms | 6.40 ms | 80.32 ms | 0 | 0 |
| 1,500 | 1,499.95 | 1.07 ms | 2.22 ms | 7.15 ms | 110.00 ms | 0 | 0 |
| 1,750 | 1,749.88 | 1.09 ms | 2.00 ms | 4.68 ms | 26.55 ms | 0 | 0 |
| 2,000 | 1,999.92 | 1.15 ms | 2.61 ms | 6.85 ms | 44.41 ms | 0 | 0 |
| 2,500 | 2,499.89 | 1.46 ms | 6.69 ms | 20.91 ms | 105.71 ms | 0 | 0 |
| 3,000 | 2,917.56 | 3.06 ms | 347.95 ms | 564.77 ms | 1,579.25 ms | 0 | 4,808 |

The 3,000 req/s step had no HTTP failures but dropped 4,808 iterations and
violated the p95 and p99 thresholds.

The 3,000 req/s retry with 2,400 preallocated VUs (2,539 maximum VUs) performed
worse: 1,838.85 req/s, 1.86% failed requests, p95 2,013.11 ms, and 65,249
dropped iterations. This indicates host/generator saturation rather than VU
shortage.

This was a single clean-state sweep on a contended 4-vCPU laptop running
Windows 11, Chrome, WSL, Docker, PHP-FPM, nginx, and k6 on the same host. The
2,500 req/s row is a single observation, not a repeatable capacity number: a
later 2,500 req/s rerun on the same host reached only 2,211.97 req/s, with p95
862.80 ms and 16,672 dropped iterations. After sustained load the host
throttled, and subsequent 2,000–2,500 req/s runs were not reproducible. Treat
these numbers as directional evidence of headroom, not as a guaranteed capacity
or as a complete-application benchmark.

## Interpretation

At the 500 req/s target, Base sustained the rate with substantial headroom:
p95 stayed far below the 100 ms threshold and there were no HTTP failures. The
saturation probe shows that this headroom extends beyond the baseline: the same
no-op endpoint sustained 2,500 req/s in a clean-state sweep and crossed the
thresholds at 3,000 req/s. That result is directional evidence about the kernel
lifecycle, not a promise for a complete application.

This does not guarantee 500 req/s for a complete application. A real endpoint
must still be measured because application latency determines the required
concurrency. With a fixed pool of 80 workers, the rough capacity limit is:

```text
throughput = workers / average request latency
```

At 500 req/s, an average latency of 160 ms would already consume all 80 workers.
At 2,500 req/s, the average latency budget drops to about 32 ms; at 3,000 req/s,
about 27 ms. The probe's p95 collapse at 3,000 req/s is consistent with the
host, not the kernel, becoming the bottleneck.

## Reproducing

Use an FPM stack with OPcache enabled, a production-like FPM pool, and a real
k6 load generator.

### Baseline (500 req/s)

- no application database or remote calls;
- OPcache enabled;
- FPM workers sized for the target concurrency;
- k6 using `constant-arrival-rate`, not VU ramping;
- a separate warmup before the measured run;
- 200 preallocated VUs (an attempt with 100 preallocated VUs dropped 143
  iterations);
- discard baseline runs with k6 `dropped_iterations` and retry with more VUs.

### Saturation probe (rate sweep)

- use the same endpoint and FPM/nginx configuration as the baseline;
- run each step for 60 seconds with `constant-arrival-rate`;
- preallocate at least 1,200 VUs for steps at or above 1,250 req/s (this run
  observed `vus_max` 1,200 through 2,500 req/s);
- if a step drops iterations, retry it once with doubled VUs (2,400 here) to
  distinguish VU shortage from host saturation;
- stop the sweep at the first step that still fails after the retry;
- run the steps back-to-back to match this probe; use cooldowns or a dedicated
  host when measuring a capacity ceiling;
- record background contention; this probe ran with Windows 11 and Chrome active
  on the same 4-vCPU host;
- measure CPU and memory outside the hot path (e.g. a cgroup snapshot before and
  after each step), not with `docker stats --no-stream` during the run;
- treat `dropped_iterations` as a failed step, not as a reason to discard the
  result;
- for a true ceiling, put k6 on a separate machine from the server.
