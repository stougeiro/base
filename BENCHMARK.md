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

## Results

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

## Interpretation

Under this workload, Base sustained the target rate with substantial headroom:
p95 remained far below the 100 ms threshold and there were no HTTP failures.

This does not guarantee 500 req/s for a complete application. A real endpoint
must still be measured because application latency determines the required
concurrency. With a fixed pool of 80 workers, the rough capacity limit is:

```text
throughput = workers / average request latency
```

At 500 req/s, an average latency of 160 ms would already consume all 80
workers.

## Reproducing

Use an FPM stack with OPcache enabled, a production-like FPM pool, and a real
k6 load generator. The important conditions are:

- no application database or remote calls for the baseline;
- OPcache enabled;
- FPM workers sized for the target concurrency;
- k6 using `constant-arrival-rate`, not VU ramping;
- separate warmup before the measured run;
- discard runs with k6 `dropped_iterations`.
